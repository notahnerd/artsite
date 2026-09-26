"""Backend tests for NFL Sim security hardening + core regression."""
import os
import time
import pytest
import requests

BASE = os.environ["REACT_APP_BACKEND_URL"].rstrip("/") if "REACT_APP_BACKEND_URL" in os.environ else None
if not BASE:
    # fallback to reading frontend/.env
    from pathlib import Path
    for line in Path("/app/frontend/.env").read_text().splitlines():
        if line.startswith("REACT_APP_BACKEND_URL="):
            BASE = line.split("=", 1)[1].strip().rstrip("/")

API = f"{BASE}/api"


@pytest.fixture(scope="module")
def season():
    r = requests.post(f"{API}/season/create", json={"user_team": "KC", "year": 2025, "difficulty": "balanced"})
    assert r.status_code == 200, r.text
    d = r.json()
    assert "id" in d and "owner_token" in d
    return d


# --- Security headers ---
def test_security_headers():
    r = requests.get(f"{API}/teams")
    assert r.status_code == 200
    h = r.headers
    assert h.get("X-Content-Type-Options") == "nosniff"
    assert h.get("X-Frame-Options") == "DENY"
    assert h.get("Referrer-Policy") == "strict-origin-when-cross-origin"
    assert "Strict-Transport-Security" in h
    assert "Permissions-Policy" in h


# --- CORS wildcard cannot co-exist with credentials ---
def test_cors_wildcard_no_credentials():
    r = requests.options(
        f"{API}/teams",
        headers={"Origin": "https://evil.example", "Access-Control-Request-Method": "GET"},
    )
    # ACAC must not be true when origins==*
    assert r.headers.get("Access-Control-Allow-Credentials", "").lower() != "true"


# --- Regression: teams + chart ---
def test_teams_regression():
    r = requests.get(f"{API}/teams")
    assert r.status_code == 200
    teams = r.json()["teams"]
    assert len(teams) == 32


def test_chart_regression():
    r = requests.get(f"{API}/chart")
    assert r.status_code == 200
    d = r.json()
    assert "run" in d and "pass" in d


# --- Create season returns + persists token; GET does not leak ---
def test_create_returns_token(season):
    assert season["owner_token"]
    assert len(season["owner_token"]) > 20


def test_get_season_strips_token(season):
    r = requests.get(f"{API}/season/{season['id']}")
    assert r.status_code == 200
    assert "owner_token" not in r.json()


# --- Ownership 403 without token, 200 with token ---
@pytest.mark.parametrize("path,payload", [
    ("/season/coaching", {"team": "KC", "philosophy": "aggressive"}),
    ("/season/depth-chart", {"team": "KC", "depth": {}}),
    ("/season/difficulty", {"difficulty": "arcade"}),
    ("/season/playbook", {"team": "KC", "pass_bias": 0.1}),
    ("/season/rival", {"team": "KC", "rival": "DEN"}),
])
def test_ownership_required(season, path, payload):
    body = {"season_id": season["id"], **payload}
    r_no = requests.post(f"{API}{path}", json=body)
    assert r_no.status_code == 403, f"{path} expected 403 got {r_no.status_code}"
    r_bad = requests.post(f"{API}{path}", json=body, headers={"X-Owner-Token": "bogus"})
    assert r_bad.status_code == 403
    r_ok = requests.post(f"{API}{path}", json=body, headers={"X-Owner-Token": season["owner_token"]})
    assert r_ok.status_code == 200, f"{path} with correct token got {r_ok.status_code}: {r_ok.text}"


def test_start_playoffs_ownership(season):
    r = requests.post(f"{API}/season/{season['id']}/start-playoffs")
    assert r.status_code == 403


# --- Team validation ---
@pytest.mark.parametrize("path,payload", [
    ("/season/coaching", {"team": "XX", "philosophy": "balanced"}),
    ("/season/depth-chart", {"team": "XX", "depth": {}}),
    ("/season/playbook", {"team": "XX", "pass_bias": 0.1}),
    ("/season/rival", {"team": "XX", "rival": "DEN"}),
])
def test_invalid_team_400(season, path, payload):
    body = {"season_id": season["id"], **payload}
    r = requests.post(f"{API}{path}", json=body, headers={"X-Owner-Token": season["owner_token"]})
    assert r.status_code == 400, f"{path} got {r.status_code}"


def test_trade_invalid_team_400(season):
    body = {
        "season_id": season["id"],
        "my_team": "XX", "my_player": "x",
        "other_team": "DEN", "other_player": "y",
    }
    r = requests.post(f"{API}/season/trade", json=body, headers={"X-Owner-Token": season["owner_token"]})
    assert r.status_code == 400


# --- Sim-week regression with owner token ---
def test_sim_week_happy_path(season):
    r = requests.post(
        f"{API}/season/sim-week",
        json={"season_id": season["id"], "week": 1},
        headers={"X-Owner-Token": season["owner_token"]},
    )
    assert r.status_code == 200, r.text
    played = r.json().get("played", [])
    assert len(played) > 0
    # Standings updated
    s = requests.get(f"{API}/season/{season['id']}/standings").json()["standings"]
    assert any((t["w"] + t["l"] + t.get("t", 0)) > 0 for t in s)


def test_sim_week_wrong_token(season):
    r = requests.post(
        f"{API}/season/sim-week",
        json={"season_id": season["id"], "week": 2},
        headers={"X-Owner-Token": "wrong"},
    )
    assert r.status_code == 403


# --- Rate limits ---
def test_create_season_rate_limit():
    # Use a persistent session so all requests hit the same ingress pod
    s = requests.Session()
    hit_429 = False
    for _ in range(25):
        r = s.post(f"{API}/season/create", json={"user_team": "KC", "year": 2025})
        if r.status_code == 429:
            hit_429 = True
            break
    assert hit_429, "Expected 429 after bursting create-season 25 times"


def test_next_year_rate_limit():
    s = requests.Session()
    # First, wait for create limit window to clear, then create a season
    time.sleep(62)
    r = s.post(f"{API}/season/create", json={"user_team": "KC"})
    assert r.status_code == 200, r.text
    sid = r.json()["id"]
    tok = r.json()["owner_token"]
    hit_429 = False
    for _ in range(15):
        rr = s.post(f"{API}/season/next-year", json={"season_id": sid}, headers={"X-Owner-Token": tok})
        if rr.status_code == 429:
            hit_429 = True
            break
    assert hit_429, "Expected 429 on next-year burst"
