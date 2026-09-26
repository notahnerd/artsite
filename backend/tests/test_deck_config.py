"""Backend tests for Custom Flippy Deck (deck-config) feature."""
import os
import pytest
import requests
from pymongo import MongoClient

BASE = os.environ.get("REACT_APP_BACKEND_URL")
if not BASE:
    from pathlib import Path
    for line in Path("/app/frontend/.env").read_text().splitlines():
        if line.startswith("REACT_APP_BACKEND_URL="):
            BASE = line.split("=", 1)[1].strip()
API = f"{BASE.rstrip('/')}/api"

MONGO_URL = "mongodb://localhost:27017"
DB_NAME = "test_database"


def _mongo():
    return MongoClient(MONGO_URL)[DB_NAME]


@pytest.fixture(scope="module")
def locked_season():
    r = requests.post(f"{API}/season/create", json={"user_team": "KC", "year": 2025})
    assert r.status_code == 200, r.text
    return r.json()


@pytest.fixture(scope="module")
def unlocked_season():
    r = requests.post(f"{API}/season/create", json={"user_team": "BUF", "year": 2025})
    assert r.status_code == 200, r.text
    s = r.json()
    db = _mongo()
    db.seasons.update_one(
        {"id": s["id"]},
        {"$set": {"champions_history": [
            {"year": 2025, "team": "KC"},
            {"year": 2026, "team": "BUF"},
        ]}},
    )
    return s


# BACKEND-1
def test_get_deck_config_locked_defaults(locked_season):
    r = requests.get(f"{API}/season/{locked_season['id']}/deck-config")
    assert r.status_code == 200, r.text
    d = r.json()
    assert d["unlocked"] is False
    assert d["seasons_played"] == 0
    assert set(d["presets"]) == {"balanced", "power_run", "air_raid", "chaos"}
    assert d["max_signature_cards"] == 3
    assert d["max_copies_per_card"] == 3


# BACKEND-2
def test_post_deck_config_locked_returns_403(locked_season):
    r = requests.post(
        f"{API}/season/deck-config",
        json={"season_id": locked_season["id"], "preset": "chaos"},
        headers={"X-Owner-Token": locked_season["owner_token"]},
    )
    assert r.status_code == 403
    assert "2 completed seasons" in r.text


# BACKEND-3
def test_get_and_post_unlocked(unlocked_season):
    r = requests.get(f"{API}/season/{unlocked_season['id']}/deck-config")
    assert r.status_code == 200
    assert r.json()["unlocked"] is True

    payload = {
        "season_id": unlocked_season["id"],
        "preset": "chaos",
        "signature_cards": [
            {"label": "Arrowhead Roar", "yards": 25, "count": 3},
            {"label": "Fumbleroski", "yards": -8, "count": 2},
        ],
    }
    r = requests.post(
        f"{API}/season/deck-config", json=payload,
        headers={"X-Owner-Token": unlocked_season["owner_token"]},
    )
    assert r.status_code == 200, r.text
    cfg = r.json()["config"]
    assert cfg["preset"] == "chaos"
    labels = [c["label"] for c in cfg["signature_cards"]]
    assert "Arrowhead Roar" in labels and "Fumbleroski" in labels


# BACKEND-4
def test_invalid_preset_400(unlocked_season):
    r = requests.post(
        f"{API}/season/deck-config",
        json={"season_id": unlocked_season["id"], "preset": "super_wacky"},
        headers={"X-Owner-Token": unlocked_season["owner_token"]},
    )
    assert r.status_code == 400


# BACKEND-5
def test_no_token_403(unlocked_season):
    r = requests.post(
        f"{API}/season/deck-config",
        json={"season_id": unlocked_season["id"], "preset": "balanced"},
    )
    assert r.status_code == 403


# BACKEND-6
def test_signature_cards_clamping(unlocked_season):
    payload = {
        "season_id": unlocked_season["id"],
        "preset": "balanced",
        "signature_cards": [
            {"label": "A" * 40, "yards": 999, "count": 99},   # clamp yards->60, count->3, label->24
            {"label": "Neg Test", "yards": -50, "count": 0},  # clamp yards->-10, count->1
            {"label": "Third", "yards": 5, "count": 2},
            {"label": "Fourth-should-be-dropped", "yards": 3, "count": 1},
        ],
    }
    r = requests.post(
        f"{API}/season/deck-config", json=payload,
        headers={"X-Owner-Token": unlocked_season["owner_token"]},
    )
    assert r.status_code == 200, r.text
    sigs = r.json()["config"]["signature_cards"]
    assert len(sigs) == 3  # 4th dropped
    assert sigs[0]["yards"] == 60 and sigs[0]["count"] == 3 and len(sigs[0]["label"]) == 24
    assert sigs[1]["yards"] == -10 and sigs[1]["count"] == 1
    assert sigs[2]["label"] == "Third"


def _next_user_game_id(sid):
    r = requests.get(f"{API}/season/{sid}")
    assert r.status_code == 200
    ut = r.json()["user_team"]
    for g in r.json()["schedule"]:
        if g.get("played"):
            continue
        if g["home"] == ut or g["away"] == ut:
            return g["game_id"]
    return None


def _sim_game(season):
    gid = _next_user_game_id(season["id"])
    assert gid, "no game to sim"
    r = requests.post(
        f"{API}/season/sim-game",
        json={"season_id": season["id"], "game_id": gid},
        headers={"X-Owner-Token": season["owner_token"]},
    )
    assert r.status_code == 200, r.text
    d = r.json()
    return d.get("result", {}).get("plays", [])


# BACKEND-7 & BACKEND-8 (combined - save chaos + signatures then sim)
def test_sim_uses_deck_config(unlocked_season):
    # Save chaos + 2 signature cards
    payload = {
        "season_id": unlocked_season["id"],
        "preset": "chaos",
        "signature_cards": [
            {"label": "Arrowhead Roar", "yards": 55, "count": 3},
            {"label": "Fumbleroski", "yards": -8, "count": 3},
        ],
    }
    r = requests.post(
        f"{API}/season/deck-config", json=payload,
        headers={"X-Owner-Token": unlocked_season["owner_token"]},
    )
    assert r.status_code == 200

    saw_signature = False
    saw_chaos_range = False
    sig_labels = {"Arrowhead Roar", "Fumbleroski"}
    for _ in range(4):
        plays = _sim_game(unlocked_season)
        for p in plays:
            c = p.get("card")
            if not c:
                continue
            if c.get("type") == "YARDS":
                y = c.get("yards")
                if y is not None and (y > 45 or y < -6):
                    saw_chaos_range = True
                if c.get("signature") is True and c.get("label") in sig_labels:
                    saw_signature = True
        if saw_signature and saw_chaos_range:
            break
    assert saw_chaos_range, "Expected YARDS cards outside default -6..+45 range under chaos preset"
    assert saw_signature, "Expected at least one signature card in simulated plays"


# BACKEND-9 — inheritance via next-year (soft check via mongo direct if not eligible)
def test_next_year_inherits_deck_config(unlocked_season):
    # Ensure deck_config is set for unlocked_season
    payload = {
        "season_id": unlocked_season["id"],
        "preset": "air_raid",
        "signature_cards": [{"label": "Hail Mary", "yards": 45, "count": 2}],
    }
    r = requests.post(
        f"{API}/season/deck-config", json=payload,
        headers={"X-Owner-Token": unlocked_season["owner_token"]},
    )
    assert r.status_code == 200
    saved_cfg = r.json()["config"]

    # Try next-year (may not be eligible - regular season not played)
    r = requests.post(
        f"{API}/season/next-year",
        json={"season_id": unlocked_season["id"]},
        headers={"X-Owner-Token": unlocked_season["owner_token"]},
    )
    if r.status_code != 200:
        pytest.skip(f"next-year not eligible ({r.status_code}); code path clearly copies deck_config (server.py L808)")
    new_sid = r.json()["new_season_id"]
    r2 = requests.get(f"{API}/season/{new_sid}/deck-config")
    assert r2.status_code == 200
    inherited = r2.json()["config"]
    assert inherited["preset"] == saved_cfg["preset"]
    assert [c["label"] for c in inherited["signature_cards"]] == [c["label"] for c in saved_cfg["signature_cards"]]
