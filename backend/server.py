from fastapi import FastAPI, APIRouter, HTTPException
from dotenv import load_dotenv
from starlette.middleware.cors import CORSMiddleware
from motor.motor_asyncio import AsyncIOMotorClient
import os
import logging
import uuid
from pathlib import Path
from pydantic import BaseModel, Field
from typing import Optional, Dict, List
from datetime import datetime, timezone

from nfl_data import TEAMS, PLAYERS, get_team, get_players
from sim_engine import simulate_full_game, RUN_CHART, PASS_CHART
from player_cards import full_card
from season import generate_schedule, compute_standings
from weather import WEATHER_TYPES, roll_weather
from playoffs import seed_playoffs, build_bracket, advance_bracket, ROUND_LABELS


ROOT_DIR = Path(__file__).parent
load_dotenv(ROOT_DIR / ".env")

mongo_url = os.environ["MONGO_URL"]
client = AsyncIOMotorClient(mongo_url)
db = client[os.environ["DB_NAME"]]

app = FastAPI(title="NFL Sim API")
api = APIRouter(prefix="/api")


# ---------- Models ----------
class CreateSeasonReq(BaseModel):
    user_team: str
    year: int = 2025


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
    game_id: str  # bracket game id


# ---------- Helpers ----------
async def _load_season(season_id):
    s = await db.seasons.find_one({"id": season_id}, {"_id": 0})
    if not s:
        raise HTTPException(404, "Season not found")
    return s


def _get_depth(season, team_id):
    return (season.get("depth_charts") or {}).get(team_id)


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
async def create_season(req: CreateSeasonReq):
    if not get_team(req.user_team):
        raise HTTPException(400, "Invalid team")
    season_id = str(uuid.uuid4())
    schedule = generate_schedule(season_id, weeks=18)
    # Pre-roll weather for each game so it's deterministic across replays
    for g in schedule:
        g["weather"] = roll_weather(g["home"])
    doc = {
        "id": season_id,
        "user_team": req.user_team,
        "year": req.year,
        "schedule": schedule,
        "current_week": 1,
        "depth_charts": {},  # team -> {pos: player_name}
        "playoffs": None,
        "created_at": datetime.now(timezone.utc).isoformat(),
    }
    await db.seasons.insert_one(doc)
    doc.pop("_id", None)
    return doc


@api.get("/season/{season_id}")
async def get_season(season_id: str):
    s = await _load_season(season_id)
    s["standings"] = compute_standings(s["schedule"])
    return s


@api.get("/season/{season_id}/standings")
async def standings(season_id: str):
    s = await _load_season(season_id)
    return {"standings": compute_standings(s["schedule"])}


@api.post("/season/sim-game")
async def sim_game(req: SimGameReq):
    s = await _load_season(req.season_id)
    game = next((g for g in s["schedule"] if g["game_id"] == req.game_id), None)
    if not game:
        raise HTTPException(404, "Game not found")
    if game["played"]:
        if game.get("log_id"):
            log = await db.game_logs.find_one({"id": game["log_id"]}, {"_id": 0})
            if log and log.get("result", {}).get("plays"):
                return {"already_played": True, "game": game, "result": log["result"]}
        result = simulate_full_game(
            game["home"], game["away"],
            weather_code=game.get("weather", "CLEAR"),
            depth_charts={game["home"]: _get_depth(s, game["home"]), game["away"]: _get_depth(s, game["away"])},
            allow_ot=False,
        )
        return {"already_played": True, "game": game, "result": result}

    result = simulate_full_game(
        game["home"], game["away"],
        weather_code=game.get("weather", "CLEAR"),
        depth_charts={game["home"]: _get_depth(s, game["home"]), game["away"]: _get_depth(s, game["away"])},
        allow_ot=False,
    )
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
    await db.seasons.update_one(
        {"id": req.season_id},
        {"$set": {"schedule": s["schedule"]}},
    )
    return {"game": game, "result": result}


@api.post("/season/sim-week")
async def sim_week(req: SimWeekReq):
    s = await _load_season(req.season_id)
    played_games = []
    for g in s["schedule"]:
        if g["week"] != req.week or g["played"]:
            continue
        result = simulate_full_game(
            g["home"], g["away"],
            weather_code=g.get("weather", "CLEAR"),
            depth_charts={g["home"]: _get_depth(s, g["home"]), g["away"]: _get_depth(s, g["away"])},
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
                "home_score": result["home_score"],
                "away_score": result["away_score"],
                "stats": result["stats"],
                "player_stats": result["player_stats"],
                "weather": result["weather"],
            },
            "created_at": datetime.now(timezone.utc).isoformat(),
        })
        g["log_id"] = log_id
        played_games.append(g)
    new_week = min(19, max(s.get("current_week", 1), req.week + 1))
    await db.seasons.update_one(
        {"id": req.season_id},
        {"$set": {"schedule": s["schedule"], "current_week": new_week}},
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
async def update_depth_chart(req: DepthChartUpdate):
    s = await _load_season(req.season_id)
    depth = s.get("depth_charts") or {}
    depth[req.team] = req.depth
    await db.seasons.update_one({"id": req.season_id}, {"$set": {"depth_charts": depth}})
    return {"depth_charts": depth}


@api.get("/season/{season_id}/depth-chart/{team}")
async def get_depth_chart(season_id: str, team: str):
    s = await _load_season(season_id)
    depth = (s.get("depth_charts") or {}).get(team, {})
    return {"team": team, "depth": depth, "roster": get_players(team)}


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
async def start_playoffs(season_id: str):
    s = await _load_season(season_id)
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
async def sim_playoff_game(req: PlayoffGameReq):
    s = await _load_season(req.season_id)
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
    result = simulate_full_game(
        game["home"], game["away"], weather_code=weather,
        depth_charts={game["home"]: _get_depth(s, game["home"]), game["away"]: _get_depth(s, game["away"])},
        allow_ot=True,
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


app.include_router(api)
app.add_middleware(
    CORSMiddleware,
    allow_credentials=True,
    allow_origins=os.environ.get("CORS_ORIGINS", "*").split(","),
    allow_methods=["*"],
    allow_headers=["*"],
)

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)


@app.on_event("shutdown")
async def shutdown_db_client():
    client.close()
