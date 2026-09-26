from fastapi import FastAPI, APIRouter, HTTPException, Request, Header
from fastapi.responses import JSONResponse
from dotenv import load_dotenv
from starlette.middleware.cors import CORSMiddleware
from starlette.middleware.base import BaseHTTPMiddleware
from motor.motor_asyncio import AsyncIOMotorClient
from slowapi import Limiter, _rate_limit_exceeded_handler
from slowapi.util import get_remote_address
from slowapi.errors import RateLimitExceeded
import os
import logging
import uuid
import secrets
from pathlib import Path
from pydantic import BaseModel, Field
from typing import Optional, Dict, List
from datetime import datetime, timezone

from nfl_data import TEAMS, PLAYERS, get_team, get_players
from sim_engine import simulate_full_game, RUN_CHART, PASS_CHART, DIFFICULTY_MULT
from player_cards import full_card
from season import generate_schedule, compute_standings
from weather import WEATHER_TYPES, roll_weather
from playoffs import seed_playoffs, build_bracket, advance_bracket, ROUND_LABELS
from injuries import active_injuries, injured_names
from franchise import roll_franchise_year
from rivalries import is_rivalry, annotate_schedule, division_rivals, LEGACY_RIVALRIES


ROOT_DIR = Path(__file__).parent
load_dotenv(ROOT_DIR / ".env")

mongo_url = os.environ["MONGO_URL"]
client = AsyncIOMotorClient(mongo_url)
db = client[os.environ["DB_NAME"]]

# ---------- Rate limiter ----------
limiter = Limiter(key_func=get_remote_address, default_limits=["120/minute"])

app = FastAPI(title="NFL Sim API")
app.state.limiter = limiter
app.add_exception_handler(RateLimitExceeded, _rate_limit_exceeded_handler)
api = APIRouter(prefix="/api")

# ---------- Security middleware ----------
MAX_BODY_BYTES = 1 * 1024 * 1024  # 1 MB


class SecurityHeadersMiddleware(BaseHTTPMiddleware):
    async def dispatch(self, request: Request, call_next):
        # Enforce request body size cap
        cl = request.headers.get("content-length")
        if cl and cl.isdigit() and int(cl) > MAX_BODY_BYTES:
            return JSONResponse({"detail": "Request body too large"}, status_code=413)
        response = await call_next(request)
        response.headers["X-Content-Type-Options"] = "nosniff"
        response.headers["X-Frame-Options"] = "DENY"
        response.headers["Referrer-Policy"] = "strict-origin-when-cross-origin"
        response.headers["Strict-Transport-Security"] = "max-age=31536000; includeSubDomains"
        response.headers["Permissions-Policy"] = "geolocation=(), microphone=(), camera=()"
        return response


app.add_middleware(SecurityHeadersMiddleware)


# ---------- Models ----------
class CreateSeasonReq(BaseModel):
    user_team: str
    year: int = 2025
    difficulty: str = "balanced"


class SimGameReq(BaseModel):
    season_id: str
    game_id: str


class SimWeekReq(BaseModel):
    season_id: str
    week: int


class DepthChartUpdate(BaseModel):
    season_id: str
    team: str
    depth: Dict[str, str]  # pos -> player name


class PlayoffGameReq(BaseModel):
    season_id: str
    game_id: str


class DifficultyReq(BaseModel):
    season_id: str
    difficulty: str


class TradeReq(BaseModel):
    season_id: str
    my_team: str
    my_player: str
    other_team: str
    other_player: str


class CoachingReq(BaseModel):
    season_id: str
    team: str
    philosophy: str  # aggressive | balanced | conservative


class NextYearReq(BaseModel):
    season_id: str


class PlaybookReq(BaseModel):
    season_id: str
    team: str
    pass_bias: float  # -0.25 to 0.25


class RivalPickReq(BaseModel):
    season_id: str
    team: str
    rival: str


# ---------- Helpers ----------
async def _load_season(season_id):
    s = await db.seasons.find_one({"id": season_id}, {"_id": 0})
    if not s:
        raise HTTPException(404, "Season not found")
    return s


async def _load_season_owned(season_id: str, x_owner_token: Optional[str]):
    """Load season and verify ownership token for mutations.
    Grandfathered: seasons created before this change have no owner_token and remain open."""
    s = await _load_season(season_id)
    expected = s.get("owner_token")
    if expected and expected != (x_owner_token or ""):
        raise HTTPException(403, "Invalid owner token")
    return s


def _validate_team(team_id: str):
    if not get_team(team_id):
        raise HTTPException(400, f"Invalid team: {team_id}")


def _get_depth(season, team_id):
    return (season.get("depth_charts") or {}).get(team_id)


def _get_injured(season, team_id, week):
    injuries = (season.get("injuries") or {}).get(team_id, [])
    return injured_names(injuries, week)


def _apply_new_injuries(season, team_id, week, new_injuries: List[Dict]):
    """Append new injuries with injured_week set."""
    if not new_injuries:
        return
    ilist = season.setdefault("injuries", {}).setdefault(team_id, [])
    for inj in new_injuries:
        ilist.append({**inj, "injured_week": week})


def _team_roster_with_trades(season, team_id):
    """Return roster including trade-added players and excluding traded-away starters.
    If franchise_rosters is present, use that as the base (multi-year mode).
    """
    fr = (season.get("franchise_rosters") or {}).get(team_id)
    base = fr if fr else get_players(team_id)
    # Ensure starter/role tags exist
    tagged = [dict(p, starter=p.get("starter", True), role=p.get("role", p.get("pos"))) for p in base]
    removed = set((season.get("trade_removed") or {}).get(team_id, []))
    filtered = [p for p in tagged if p["name"] not in removed]
    added = (season.get("trade_players") or {}).get(team_id, [])
    return filtered + [dict(p, starter=True) for p in added]


def _coaching(season):
    return season.get("coaching") or {}


def _playbook(season):
    return season.get("playbook") or {}


def _is_rivalry(season, home, away):
    return is_rivalry(home, away, season.get("extra_rivals") or {})


# ---------- Routes ----------
@api.get("/")
async def root():
    return {"message": "NFL Sim API", "version": "2.0"}


@api.get("/teams")
async def list_teams():
    return {"teams": TEAMS}


@api.get("/teams/{team_id}")
async def get_team_detail(team_id: str):
    t = get_team(team_id)
    if not t:
        raise HTTPException(404, "Team not found")
    return {"team": t, "players": get_players(team_id)}


@api.get("/chart")
async def get_chart():
    return {"run": RUN_CHART, "pass": PASS_CHART}


@api.get("/player-card")
async def player_card(team: str, name: str):
    players = get_players(team)
    p = next((x for x in players if x["name"] == name), None)
    if not p:
        raise HTTPException(404, "Player not found")
    return {"player": p, "card": full_card(p)}


@api.get("/team-cards/{team_id}")
async def team_cards(team_id: str):
    players = get_players(team_id)
    return {
        "team": get_team(team_id),
        "cards": [{"player": p, "card": full_card(p)} for p in players],
    }


@api.get("/weather-types")
async def weather_types():
    return {"types": WEATHER_TYPES}


# ---------- Season ----------
@api.post("/season/create")
@limiter.limit("10/minute")
async def create_season(request: Request, req: CreateSeasonReq):
    if not get_team(req.user_team):
        raise HTTPException(400, "Invalid team")
    season_id = str(uuid.uuid4())
    owner_token = secrets.token_urlsafe(32)
    schedule = generate_schedule(season_id, weeks=18)
    # Pre-roll weather for each game so it's deterministic across replays
    for g in schedule:
        g["weather"] = roll_weather(g["home"])
    annotate_schedule(schedule)
    doc = {
        "id": season_id,
        "owner_token": owner_token,
        "user_team": req.user_team,
        "year": req.year,
        "difficulty": req.difficulty if req.difficulty in DIFFICULTY_MULT else "balanced",
        "schedule": schedule,
        "current_week": 1,
        "depth_charts": {},
        "injuries": {},   # team_id -> [{player, pos, weeks_out, desc, injured_week}]
        "trades": [],     # list of trade records
        "trade_players": {},  # team_id -> list of added player dicts (from trades)
        "trade_removed": {},  # team_id -> list of removed starter names
        "playoffs": None,
        "created_at": datetime.now(timezone.utc).isoformat(),
    }
    await db.seasons.insert_one(doc)
    doc.pop("_id", None)
    # Do NOT leak owner_token in listing/reads — but return it once on create so client can persist it.
    return doc


@api.get("/season/{season_id}")
async def get_season(season_id: str):
    s = await _load_season(season_id)
    s.pop("owner_token", None)
    s["standings"] = compute_standings(s["schedule"])
    return s


@api.get("/season/{season_id}/standings")
async def standings(season_id: str):
    s = await _load_season(season_id)
    return {"standings": compute_standings(s["schedule"])}


@api.post("/season/sim-game")
@limiter.limit("60/minute")
async def sim_game(request: Request, req: SimGameReq, x_owner_token: Optional[str] = Header(None)):
    s = await _load_season_owned(req.season_id, x_owner_token)
    game = next((g for g in s["schedule"] if g["game_id"] == req.game_id), None)
    if not game:
        raise HTTPException(404, "Game not found")
    week = game["week"]
    common_kwargs = dict(
        weather_code=game.get("weather", "CLEAR"),
        depth_charts={game["home"]: _get_depth(s, game["home"]), game["away"]: _get_depth(s, game["away"])},
        injuries={game["home"]: _get_injured(s, game["home"], week), game["away"]: _get_injured(s, game["away"], week)},
        difficulty=s.get("difficulty", "balanced"),
        custom_rosters={game["home"]: _team_roster_with_trades(s, game["home"]),
                        game["away"]: _team_roster_with_trades(s, game["away"])},
        coaching=_coaching(s),
        playbook=_playbook(s),
        rivalry=game.get("rivalry", False) or _is_rivalry(s, game["home"], game["away"]),
    )
    if game["played"]:
        if game.get("log_id"):
            log = await db.game_logs.find_one({"id": game["log_id"]}, {"_id": 0})
            if log and log.get("result", {}).get("plays"):
                return {"already_played": True, "game": game, "result": log["result"]}
        result = simulate_full_game(game["home"], game["away"], allow_ot=False, roll_new_injuries=False, **common_kwargs)
        return {"already_played": True, "game": game, "result": result}

    result = simulate_full_game(game["home"], game["away"], allow_ot=False, **common_kwargs)
    game["played"] = True
    game["home_score"] = result["home_score"]
    game["away_score"] = result["away_score"]
    game_log_id = str(uuid.uuid4())
    await db.game_logs.insert_one({
        "id": game_log_id,
        "season_id": req.season_id,
        "game_id": req.game_id,
        "result": result,
        "created_at": datetime.now(timezone.utc).isoformat(),
    })
    game["log_id"] = game_log_id
    # Apply injuries to season doc
    _apply_new_injuries(s, game["home"], week, result.get("new_injuries", {}).get(game["home"], []))
    _apply_new_injuries(s, game["away"], week, result.get("new_injuries", {}).get(game["away"], []))
    await db.seasons.update_one(
        {"id": req.season_id},
        {"$set": {"schedule": s["schedule"], "injuries": s.get("injuries", {})}},
    )
    return {"game": game, "result": result}


@api.post("/season/sim-week")
@limiter.limit("30/minute")
async def sim_week(request: Request, req: SimWeekReq, x_owner_token: Optional[str] = Header(None)):
    s = await _load_season_owned(req.season_id, x_owner_token)
    played_games = []
    for g in s["schedule"]:
        if g["week"] != req.week or g["played"]:
            continue
        result = simulate_full_game(
            g["home"], g["away"],
            weather_code=g.get("weather", "CLEAR"),
            depth_charts={g["home"]: _get_depth(s, g["home"]), g["away"]: _get_depth(s, g["away"])},
            injuries={g["home"]: _get_injured(s, g["home"], g["week"]), g["away"]: _get_injured(s, g["away"], g["week"])},
            difficulty=s.get("difficulty", "balanced"),
            custom_rosters={g["home"]: _team_roster_with_trades(s, g["home"]),
                            g["away"]: _team_roster_with_trades(s, g["away"])},
            coaching=_coaching(s),
            playbook=_playbook(s),
            rivalry=g.get("rivalry", False) or _is_rivalry(s, g["home"], g["away"]),
            allow_ot=False,
        )
        g["played"] = True
        g["home_score"] = result["home_score"]
        g["away_score"] = result["away_score"]
        log_id = str(uuid.uuid4())
        await db.game_logs.insert_one({
            "id": log_id,
            "season_id": req.season_id,
            "game_id": g["game_id"],
            "result": {
                "home": g["home"], "away": g["away"],
                "home_score": result["home_score"],
                "away_score": result["away_score"],
                "stats": result["stats"],
                "player_stats": result["player_stats"],
                "weather": result["weather"],
                "plays": result["plays"],
            },
            "created_at": datetime.now(timezone.utc).isoformat(),
        })
        g["log_id"] = log_id
        _apply_new_injuries(s, g["home"], g["week"], result.get("new_injuries", {}).get(g["home"], []))
        _apply_new_injuries(s, g["away"], g["week"], result.get("new_injuries", {}).get(g["away"], []))
        played_games.append(g)
    new_week = min(19, max(s.get("current_week", 1), req.week + 1))
    await db.seasons.update_one(
        {"id": req.season_id},
        {"$set": {"schedule": s["schedule"], "current_week": new_week, "injuries": s.get("injuries", {})}},
    )
    return {"played": played_games, "current_week": new_week}


@api.get("/game-log/{log_id}")
async def game_log(log_id: str):
    log = await db.game_logs.find_one({"id": log_id}, {"_id": 0})
    if not log:
        raise HTTPException(404, "Log not found")
    return log


# ---------- Depth chart ----------
@api.post("/season/depth-chart")
async def update_depth_chart(req: DepthChartUpdate, x_owner_token: Optional[str] = Header(None)):
    _validate_team(req.team)
    s = await _load_season_owned(req.season_id, x_owner_token)
    depth = s.get("depth_charts") or {}
    depth[req.team] = req.depth
    await db.seasons.update_one({"id": req.season_id}, {"$set": {"depth_charts": depth}})
    return {"depth_charts": depth}


@api.get("/season/{season_id}/depth-chart/{team}")
async def get_depth_chart(season_id: str, team: str):
    s = await _load_season(season_id)
    depth = (s.get("depth_charts") or {}).get(team, {})
    roster = _team_roster_with_trades(s, team)
    week = s.get("current_week", 1)
    inj = _get_injured(s, team, week)
    for p in roster:
        if p["name"] in inj:
            p["injured"] = True
    return {"team": team, "depth": depth, "roster": roster}


# ---------- Injuries ----------
@api.get("/season/{season_id}/injuries")
async def list_injuries(season_id: str):
    s = await _load_season(season_id)
    week = s.get("current_week", 1)
    out = {}
    for tid, ilist in (s.get("injuries") or {}).items():
        act = active_injuries(ilist, week)
        if act:
            out[tid] = [dict(i, weeks_remaining=(i["injured_week"] + i["weeks_out"] - week)) for i in act]
    return {"week": week, "injuries": out}


# ---------- Difficulty ----------
@api.post("/season/difficulty")
async def set_difficulty(req: DifficultyReq, x_owner_token: Optional[str] = Header(None)):
    if req.difficulty not in DIFFICULTY_MULT:
        raise HTTPException(400, "Invalid difficulty")
    await _load_season_owned(req.season_id, x_owner_token)
    await db.seasons.update_one({"id": req.season_id}, {"$set": {"difficulty": req.difficulty}})
    return {"difficulty": req.difficulty}


@api.get("/difficulties")
async def list_difficulties():
    return {
        "options": [
            {"code": "arcade", "label": "Arcade", "desc": "High-scoring shootouts. Full card yardage."},
            {"code": "balanced", "label": "Balanced", "desc": "Realistic-ish scoring, some big plays."},
            {"code": "realistic", "label": "Realistic", "desc": "Grounded low-scoring games. Modest yardage."},
        ],
    }


# ---------- Trades ----------
@api.post("/season/trade")
async def make_trade(req: TradeReq, x_owner_token: Optional[str] = Header(None)):
    _validate_team(req.my_team)
    _validate_team(req.other_team)
    s = await _load_season_owned(req.season_id, x_owner_token)
    if s.get("current_week", 1) != 8:
        raise HTTPException(400, "Trades only allowed at Week 8")
    my_roster = _team_roster_with_trades(s, req.my_team)
    other_roster = _team_roster_with_trades(s, req.other_team)
    my_player = next((p for p in my_roster if p["name"] == req.my_player), None)
    other_player = next((p for p in other_roster if p["name"] == req.other_player), None)
    if not my_player or not other_player:
        raise HTTPException(400, "Player not found")
    if my_player.get("pos") != other_player.get("pos"):
        raise HTTPException(400, "Trades must be same position")
    # Track: remove my_player from my_team; remove other_player from other_team;
    # add my_player to other_team; add other_player to my_team.
    removed = s.setdefault("trade_removed", {})
    added = s.setdefault("trade_players", {})
    removed.setdefault(req.my_team, []).append(my_player["name"])
    removed.setdefault(req.other_team, []).append(other_player["name"])
    added.setdefault(req.other_team, []).append({**my_player, "starter": True})
    added.setdefault(req.my_team, []).append({**other_player, "starter": True})
    trade_record = {
        "week": s.get("current_week"),
        "my_team": req.my_team, "my_player": my_player,
        "other_team": req.other_team, "other_player": other_player,
    }
    s.setdefault("trades", []).append(trade_record)
    await db.seasons.update_one(
        {"id": req.season_id},
        {"$set": {"trade_removed": removed, "trade_players": added, "trades": s["trades"]}},
    )
    return {"trade": trade_record, "trades": s["trades"]}


@api.get("/season/{season_id}/tradeable-players")
async def tradeable_players(season_id: str, exclude_team: str):
    """List candidate targets from other teams (only starters)."""
    s = await _load_season(season_id)
    out = []
    for t in TEAMS:
        if t["id"] == exclude_team:
            continue
        roster = _team_roster_with_trades(s, t["id"])
        starters = [p for p in roster if p.get("starter") and p["pos"] in ("QB", "RB", "WR", "TE", "K")]
        out.append({"team": t, "players": starters})
    return {"teams": out, "current_week": s.get("current_week", 1)}


# ---------- Playoff Race ----------
@api.get("/season/{season_id}/playoff-race")
async def playoff_race(season_id: str):
    s = await _load_season(season_id)
    standings = compute_standings(s["schedule"])
    afc = [x for x in standings if x["conf"] == "AFC"]
    nfc = [x for x in standings if x["conf"] == "NFC"]

    def _classify(conf_list):
        result = []
        for i, t in enumerate(conf_list):
            if i < 7:
                status = "IN"
            elif i < 10:
                status = "BUBBLE"
            else:
                status = "OUT"
            result.append({**t, "seed": i + 1, "status": status})
        return result

    return {
        "AFC": _classify(afc),
        "NFC": _classify(nfc),
        "current_week": s.get("current_week", 1),
    }


# ---------- Stat Leaders ----------
@api.get("/season/{season_id}/leaders")
async def leaders(season_id: str, category: str = "all"):
    s = await _load_season(season_id)
    # Collect log ids from schedule + playoffs
    log_ids = [g["log_id"] for g in s["schedule"] if g.get("log_id")]
    if s.get("playoffs"):
        log_ids += [g["log_id"] for g in s["playoffs"]["games"] if g.get("log_id")]

    combined: Dict[str, Dict] = {}
    logs = await db.game_logs.find(
        {"id": {"$in": log_ids}},
        {"_id": 0, "result.player_stats": 1}
    ).to_list(1000)
    for lg in logs:
        pstats = lg.get("result", {}).get("player_stats") or {}
        for name, stat in pstats.items():
            base = combined.setdefault(name, {**stat})
            if base is stat:
                continue
            for k, v in stat.items():
                if isinstance(v, (int, float)):
                    base[k] = base.get(k, 0) + v

    leaders_out = {
        "passing": sorted(combined.values(), key=lambda x: -x.get("pass_yds", 0))[:10],
        "rushing": sorted(combined.values(), key=lambda x: -x.get("rush_yds", 0))[:10],
        "receiving": sorted(combined.values(), key=lambda x: -x.get("rec_yds", 0))[:10],
        "sacks": sorted(combined.values(), key=lambda x: -x.get("sacks", 0))[:10],
    }
    return leaders_out


# ---------- Playoffs ----------
@api.post("/season/{season_id}/start-playoffs")
async def start_playoffs(season_id: str, x_owner_token: Optional[str] = Header(None)):
    s = await _load_season_owned(season_id, x_owner_token)
    all_played = all(g["played"] for g in s["schedule"])
    if not all_played:
        raise HTTPException(400, "Regular season not complete")
    standings = compute_standings(s["schedule"])
    seeds = seed_playoffs(standings)
    bracket = build_bracket(seeds)
    # attach weather for the games that have real teams now
    for g in bracket["games"]:
        if g["home"] and g["away"]:
            g["weather"] = roll_weather(g["home"])
    await db.seasons.update_one({"id": season_id}, {"$set": {"playoffs": bracket}})
    return bracket


@api.post("/season/playoff-game")
@limiter.limit("60/minute")
async def sim_playoff_game(request: Request, req: PlayoffGameReq, x_owner_token: Optional[str] = Header(None)):
    s = await _load_season_owned(req.season_id, x_owner_token)
    bracket = s.get("playoffs")
    if not bracket:
        raise HTTPException(400, "Playoffs not started")
    game = next((g for g in bracket["games"] if g["id"] == req.game_id), None)
    if not game:
        raise HTTPException(404, "Playoff game not found")
    if not game["home"] or not game["away"]:
        raise HTTPException(400, "Game not ready (previous round not complete)")
    if game["played"] and game.get("log_id"):
        log = await db.game_logs.find_one({"id": game["log_id"]}, {"_id": 0})
        if log and log.get("result", {}).get("plays"):
            return {"already_played": True, "game": game, "result": log["result"]}
    weather = game.get("weather") or roll_weather(game["home"])
    game["weather"] = weather
    week = 19  # playoffs = post-regular season
    result = simulate_full_game(
        game["home"], game["away"], weather_code=weather,
        depth_charts={game["home"]: _get_depth(s, game["home"]), game["away"]: _get_depth(s, game["away"])},
        injuries={game["home"]: _get_injured(s, game["home"], week), game["away"]: _get_injured(s, game["away"], week)},
        difficulty=s.get("difficulty", "balanced"),
        custom_rosters={game["home"]: _team_roster_with_trades(s, game["home"]),
                        game["away"]: _team_roster_with_trades(s, game["away"])},
        coaching=_coaching(s),
        playbook=_playbook(s),
        rivalry=True,  # playoff games always intense
        allow_ot=True,
        roll_new_injuries=False,
    )
    game["played"] = True
    game["home_score"] = result["home_score"]
    game["away_score"] = result["away_score"]
    winner = game["home"] if result["home_score"] > result["away_score"] else game["away"]
    game["winner"] = winner
    log_id = str(uuid.uuid4())
    await db.game_logs.insert_one({
        "id": log_id,
        "season_id": req.season_id,
        "game_id": req.game_id,
        "result": result,
        "created_at": datetime.now(timezone.utc).isoformat(),
    })
    game["log_id"] = log_id
    # Advance bracket
    bracket = advance_bracket(bracket, req.game_id, winner)
    # After advancing, roll weather for newly filled games
    for g in bracket["games"]:
        if g["home"] and g["away"] and not g.get("weather"):
            g["weather"] = roll_weather(g["home"])
    await db.seasons.update_one({"id": req.season_id}, {"$set": {"playoffs": bracket}})
    return {"game": game, "result": result, "bracket": bracket}


# ---------- Playbook & Rivalry ----------
@api.post("/season/playbook")
async def set_playbook(req: PlaybookReq, x_owner_token: Optional[str] = Header(None)):
    _validate_team(req.team)
    bias = max(-0.25, min(0.25, req.pass_bias))
    s = await _load_season_owned(req.season_id, x_owner_token)
    pb = s.get("playbook") or {}
    pb[req.team] = bias
    await db.seasons.update_one({"id": req.season_id}, {"$set": {"playbook": pb}})
    return {"playbook": pb}


@api.post("/season/rival")
async def set_rival(req: RivalPickReq, x_owner_token: Optional[str] = Header(None)):
    _validate_team(req.team)
    if not get_team(req.rival):
        raise HTTPException(400, "Invalid rival team")
    s = await _load_season_owned(req.season_id, x_owner_token)
    extras = s.get("extra_rivals") or {}
    extras[req.team] = req.rival
    # Re-annotate schedule so rivalry games reflect the pick
    for g in s["schedule"]:
        g["rivalry"] = is_rivalry(g["home"], g["away"], extras)
    await db.seasons.update_one(
        {"id": req.season_id},
        {"$set": {"extra_rivals": extras, "schedule": s["schedule"]}},
    )
    return {"extra_rivals": extras}


@api.get("/season/{season_id}/rivals/{team_id}")
async def team_rivals(season_id: str, team_id: str):
    s = await _load_season(season_id)
    extras = s.get("extra_rivals") or {}
    return {
        "division_rivals": division_rivals(team_id),
        "legacy_rivals": [pair for pair in [list(x) for x in LEGACY_RIVALRIES] if team_id in pair],
        "extra_rival": extras.get(team_id),
    }


# ---------- Coaching Philosophy ----------
@api.post("/season/coaching")
async def set_coaching(req: CoachingReq, x_owner_token: Optional[str] = Header(None)):
    if req.philosophy not in ("aggressive", "balanced", "conservative"):
        raise HTTPException(400, "Invalid philosophy")
    _validate_team(req.team)
    s = await _load_season_owned(req.season_id, x_owner_token)
    coaching = s.get("coaching") or {}
    coaching[req.team] = req.philosophy
    await db.seasons.update_one({"id": req.season_id}, {"$set": {"coaching": coaching}})
    return {"coaching": coaching}


@api.get("/season/{season_id}/coaching")
async def get_coaching(season_id: str):
    s = await _load_season(season_id)
    return {"coaching": s.get("coaching") or {}}


# ---------- Franchise (multi-year) ----------
@api.post("/season/next-year")
@limiter.limit("5/minute")
async def next_year(request: Request, req: NextYearReq, x_owner_token: Optional[str] = Header(None)):
    s = await _load_season_owned(req.season_id, x_owner_token)
    # Ensure playoffs done (super bowl champion set) or regular season done
    playoffs = s.get("playoffs") or {}
    champion = playoffs.get("champion")

    # Compute new year data
    changes = roll_franchise_year(s)
    # Build franchise_rosters map for next season
    new_rosters = {tid: c["roster"] for tid, c in changes.items()}
    retirements = {tid: c["retired"] for tid, c in changes.items() if c["retired"]}
    rookies = {tid: c["rookies"] for tid, c in changes.items()}

    # Champions history: propagate + append this year's champion
    history = list(s.get("champions_history") or [])
    if champion:
        history.append({"year": s.get("year", 2025), "team": champion})
    franchise_id = s.get("franchise_id") or s["id"]
    next_year_num = (s.get("year") or 2025) + 1

    # Create new season doc (inherits ownership from previous season)
    new_season_id = str(uuid.uuid4())
    new_owner_token = s.get("owner_token") or secrets.token_urlsafe(32)
    schedule = generate_schedule(new_season_id, weeks=18)
    for g in schedule:
        g["weather"] = roll_weather(g["home"])
    new_doc = {
        "id": new_season_id,
        "owner_token": new_owner_token,
        "user_team": s["user_team"],
        "year": next_year_num,
        "difficulty": s.get("difficulty", "balanced"),
        "schedule": schedule,
        "current_week": 1,
        "depth_charts": {},
        "injuries": {},
        "trades": [],
        "trade_players": {},
        "trade_removed": {},
        "playoffs": None,
        "coaching": s.get("coaching") or {},
        "franchise_id": franchise_id,
        "prev_season_id": s["id"],
        "franchise_rosters": new_rosters,
        "champions_history": history,
        "last_year_retirements": retirements,
        "last_year_rookies": rookies,
        "created_at": datetime.now(timezone.utc).isoformat(),
    }
    await db.seasons.insert_one(new_doc)
    new_doc.pop("_id", None)
    return {
        "new_season_id": new_season_id,
        "owner_token": new_owner_token,
        "retirements": retirements,
        "rookies": rookies,
        "champions_history": history,
        "year": next_year_num,
    }


@api.get("/season/{season_id}/franchise")
async def franchise_summary(season_id: str):
    s = await _load_season(season_id)
    return {
        "franchise_id": s.get("franchise_id") or s["id"],
        "year": s.get("year", 2025),
        "champions_history": s.get("champions_history") or [],
        "last_year_retirements": s.get("last_year_retirements") or {},
        "last_year_rookies": s.get("last_year_rookies") or {},
    }


# ---------- Game log (public share) ----------
@api.get("/game-log/{log_id}/share")
async def share_game(log_id: str):
    log = await db.game_logs.find_one({"id": log_id}, {"_id": 0})
    if not log:
        raise HTTPException(404, "Log not found")
    result = log.get("result", {})
    plays = result.get("plays", [])
    # Top plays: scoring events + biggest gains
    top = []
    for p in plays:
        if p.get("result") in ("TD", "FG_GOOD", "INT", "FUMBLE") or p.get("yards", 0) >= 25:
            top.append(p)
    top = top[:12]
    return {
        "log_id": log_id,
        "home": result.get("home"),
        "away": result.get("away"),
        "home_score": result.get("home_score"),
        "away_score": result.get("away_score"),
        "stats": result.get("stats"),
        "weather": result.get("weather"),
        "top_plays": top,
    }


app.include_router(api)

_cors_env = os.environ.get("CORS_ORIGINS", "*").strip()
_cors_origins = [o.strip() for o in _cors_env.split(",") if o.strip()]
# If wildcard, disable credentials (spec violation + hardening: audit SEC P3)
_allow_credentials = not (len(_cors_origins) == 1 and _cors_origins[0] == "*")
app.add_middleware(
    CORSMiddleware,
    allow_credentials=_allow_credentials,
    allow_origins=_cors_origins,
    allow_methods=["GET", "POST", "OPTIONS"],
    allow_headers=["Content-Type", "X-Owner-Token", "Authorization"],
)

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)


@app.on_event("shutdown")
async def shutdown_db_client():
    client.close()
