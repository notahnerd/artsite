from fastapi import FastAPI, APIRouter, HTTPException
from dotenv import load_dotenv
from starlette.middleware.cors import CORSMiddleware
from motor.motor_asyncio import AsyncIOMotorClient
import os
import logging
import uuid
from pathlib import Path
from pydantic import BaseModel, Field
from typing import Optional
from datetime import datetime, timezone

from nfl_data import TEAMS, PLAYERS, get_team, get_players
from sim_engine import simulate_full_game, RUN_CHART, PASS_CHART
from player_cards import full_card, qb_card, rb_card, wr_card
from season import generate_schedule, compute_standings


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


# ---------- Routes ----------
@api.get("/")
async def root():
    return {"message": "NFL Sim API", "version": "1.0"}


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


@api.post("/season/create")
async def create_season(req: CreateSeasonReq):
    if not get_team(req.user_team):
        raise HTTPException(400, "Invalid team")
    season_id = str(uuid.uuid4())
    schedule = generate_schedule(season_id, weeks=18)
    doc = {
        "id": season_id,
        "user_team": req.user_team,
        "year": req.year,
        "schedule": schedule,
        "current_week": 1,
        "created_at": datetime.now(timezone.utc).isoformat(),
    }
    await db.seasons.insert_one(doc)
    doc.pop("_id", None)
    return doc


@api.get("/season/{season_id}")
async def get_season(season_id: str):
    s = await db.seasons.find_one({"id": season_id}, {"_id": 0})
    if not s:
        raise HTTPException(404, "Season not found")
    s["standings"] = compute_standings(s["schedule"])
    return s


@api.get("/season/{season_id}/standings")
async def standings(season_id: str):
    s = await db.seasons.find_one({"id": season_id}, {"_id": 0})
    if not s:
        raise HTTPException(404, "Season not found")
    return {"standings": compute_standings(s["schedule"])}


@api.post("/season/sim-game")
async def sim_game(req: SimGameReq):
    s = await db.seasons.find_one({"id": req.season_id}, {"_id": 0})
    if not s:
        raise HTTPException(404, "Season not found")
    game = next((g for g in s["schedule"] if g["game_id"] == req.game_id), None)
    if not game:
        raise HTTPException(404, "Game not found")
    if game["played"]:
        # Return cached log if available
        if game.get("log_id"):
            log = await db.game_logs.find_one({"id": game["log_id"]}, {"_id": 0})
            if log and log.get("result", {}).get("plays"):
                return {"already_played": True, "game": game, "result": log["result"]}
        # Fallback: replay simulation
        result = simulate_full_game(game["home"], game["away"])
        return {"already_played": True, "game": game, "result": result}
    result = simulate_full_game(game["home"], game["away"])
    game["played"] = True
    game["home_score"] = result["home_score"]
    game["away_score"] = result["away_score"]
    # store the play log for review
    game_log_id = str(uuid.uuid4())
    await db.game_logs.insert_one({
        "id": game_log_id,
        "season_id": req.season_id,
        "game_id": req.game_id,
        "result": result,
        "created_at": datetime.now(timezone.utc).isoformat(),
    })
    game["log_id"] = game_log_id
    await db.seasons.update_one({"id": req.season_id}, {"$set": {"schedule": s["schedule"]}})
    return {"game": game, "result": result}


@api.post("/season/sim-week")
async def sim_week(req: SimWeekReq):
    s = await db.seasons.find_one({"id": req.season_id}, {"_id": 0})
    if not s:
        raise HTTPException(404, "Season not found")
    played_games = []
    for g in s["schedule"]:
        if g["week"] != req.week or g["played"]:
            continue
        result = simulate_full_game(g["home"], g["away"])
        g["played"] = True
        g["home_score"] = result["home_score"]
        g["away_score"] = result["away_score"]
        # save short log
        log_id = str(uuid.uuid4())
        await db.game_logs.insert_one({
            "id": log_id,
            "season_id": req.season_id,
            "game_id": g["game_id"],
            "result": {"home_score": result["home_score"], "away_score": result["away_score"], "stats": result["stats"]},
            "created_at": datetime.now(timezone.utc).isoformat(),
        })
        g["log_id"] = log_id
        played_games.append(g)
    # advance current_week
    new_week = min(18, max(s.get("current_week", 1), req.week + 1))
    await db.seasons.update_one({"id": req.season_id}, {"$set": {"schedule": s["schedule"], "current_week": new_week}})
    return {"played": played_games, "current_week": new_week}


@api.get("/game-log/{log_id}")
async def game_log(log_id: str):
    log = await db.game_logs.find_one({"id": log_id}, {"_id": 0})
    if not log:
        raise HTTPException(404, "Log not found")
    return log


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
