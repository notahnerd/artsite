"""Tabletop NFL simulation engine (Strat-O-Matic style).
Each play resolves through PLAYER CARDS (see player_cards.py):
- Pass: QB card blended with target WR/TE card; team DEF adjusts
- Run: RB card; team DEF adjusts
Team ratings still act as tiebreakers via def_modifier inside cards.
"""
import random
from typing import Dict, List, Tuple

from nfl_data import get_team, get_players
from player_cards import resolve_pass, resolve_run, qb_card, rb_card


# ---- Chart: base yards by 2D6 roll for RUN and PASS ----
# This is the visible chart the user can inspect.
RUN_CHART = {
    2: {"yards": -4, "event": "TFL"},
    3: {"yards": -1, "event": "NORMAL"},
    4: {"yards": 1,  "event": "NORMAL"},
    5: {"yards": 2,  "event": "NORMAL"},
    6: {"yards": 3,  "event": "NORMAL"},
    7: {"yards": 4,  "event": "NORMAL"},
    8: {"yards": 6,  "event": "NORMAL"},
    9: {"yards": 9,  "event": "NORMAL"},
    10: {"yards": 14, "event": "NORMAL"},
    11: {"yards": 22, "event": "BIG_PLAY"},
    12: {"yards": 40, "event": "BREAKAWAY"},
}
PASS_CHART = {
    2: {"yards": 0,  "event": "INT"},
    3: {"yards": -7, "event": "SACK"},
    4: {"yards": 0,  "event": "INCOMPLETE"},
    5: {"yards": 0,  "event": "INCOMPLETE"},
    6: {"yards": 5,  "event": "NORMAL"},
    7: {"yards": 7,  "event": "NORMAL"},
    8: {"yards": 10, "event": "NORMAL"},
    9: {"yards": 14, "event": "NORMAL"},
    10: {"yards": 20, "event": "NORMAL"},
    11: {"yards": 32, "event": "BIG_PLAY"},
    12: {"yards": 50, "event": "DEEP_BOMB"},
}


def roll_2d6() -> Tuple[int, int]:
    return random.randint(1, 6), random.randint(1, 6)


def rating_modifier(off_rating: int, def_rating: int) -> int:
    """Returns modifier to yards. Every 8 rating diff = +/-1 yard base scaling."""
    return round((off_rating - def_rating) / 8)


def choose_play_type(down: int, distance: int, ball_on: int, score_diff: int) -> str:
    """Simple AI to pick play type.
    ball_on: yards from own goal line (0-100), so ball_on=75 means at opp 25.
    """
    yards_to_goal = 100 - ball_on
    # 4th down logic
    if down == 4:
        if yards_to_goal <= 35:
            return "FG"
        if distance <= 2 and ball_on > 50:
            # go for it (rare)
            return "RUN" if random.random() < 0.5 else "PASS"
        return "PUNT"
    # Late game trailing -> pass more
    pass_prob = 0.55
    if score_diff < -7:
        pass_prob = 0.75
    if distance >= 8:
        pass_prob = min(0.85, pass_prob + 0.15)
    if distance <= 2:
        pass_prob = 0.35
    return "PASS" if random.random() < pass_prob else "RUN"


def _pick_receiver(players: List[Dict]) -> Dict:
    """Weighted pick between WRs and TE."""
    pool = [p for p in players if p["pos"] in ("WR", "TE", "RB")]
    if not pool:
        return {"name": "Receiver"}
    weights = [(p["ovr"] - 65) ** 2 for p in pool]
    return random.choices(pool, weights=weights, k=1)[0]


def simulate_play(state: Dict, teams: Dict, players: Dict) -> Dict:
    """Simulate one play. Mutates state and returns event dict."""
    off_id = state["possession"]
    def_id = state["home"] if off_id == state["away"] else state["away"]
    off_team = teams[off_id]
    def_team = teams[def_id]
    off_players = players[off_id]
    def_players = players[def_id]

    down = state["down"]
    distance = state["distance"]
    ball_on = state["ball_on"]  # 0-100, 0 = own goal, 100 = opp goal
    score_diff = state[f"{off_id}_score"] - state[f"{def_id}_score"]

    play_type = choose_play_type(down, distance, ball_on, score_diff)

    d1, d2 = roll_2d6()
    roll = d1 + d2

    event = {
        "quarter": state["quarter"],
        "clock": state["clock"],
        "off": off_id,
        "def": def_id,
        "down": down,
        "distance": distance,
        "ball_on": ball_on,
        "play_type": play_type,
        "dice": [d1, d2],
        "roll": roll,
        "yards": 0,
        "result": "NORMAL",
        "description": "",
        "score_change": None,
        "turnover": False,
    }

    qb = next((p for p in off_players if p["pos"] == "QB"), {"name": "QB"})
    rb = next((p for p in off_players if p["pos"] == "RB"), {"name": "RB"})
    kicker = next((p for p in off_players if p["pos"] == "K"), {"name": "K", "ovr": 80})

    if play_type == "PUNT":
        punt_dist = random.randint(38, 55)
        new_ball = max(20, min(95, ball_on + punt_dist))
        # touchback logic
        if new_ball >= 100:
            new_ball_flipped = 100 - 75  # touchback -> other team at own 25
        else:
            new_ball_flipped = 100 - new_ball
        event["yards"] = punt_dist
        event["result"] = "PUNT"
        event["description"] = f"{off_team['name']} punt {punt_dist} yards. {def_team['name']} takes over."
        state["possession"] = def_id
        state["ball_on"] = new_ball_flipped
        state["down"] = 1
        state["distance"] = 10
        _advance_clock(state, 12)
        return event

    if play_type == "FG":
        yards_to_goal = 100 - ball_on
        fg_distance = yards_to_goal + 17
        # success chance based on kicker OVR and distance
        base = max(0.35, (kicker.get("ovr", 80) - 60) / 40)
        if fg_distance <= 35:
            chance = base + 0.35
        elif fg_distance <= 45:
            chance = base + 0.15
        elif fg_distance <= 55:
            chance = base - 0.05
        else:
            chance = base - 0.25
        chance = max(0.15, min(0.98, chance))
        success = random.random() < chance
        event["yards"] = fg_distance
        if success:
            event["result"] = "FG_GOOD"
            event["score_change"] = {"team": off_id, "points": 3}
            event["description"] = f"{kicker['name']} nails a {fg_distance}-yard field goal! GOOD."
            state[f"{off_id}_score"] += 3
            _score_flip(state, def_id)
        else:
            event["result"] = "FG_MISS"
            event["description"] = f"{kicker['name']}'s {fg_distance}-yard FG attempt is NO GOOD."
            state["possession"] = def_id
            state["ball_on"] = 100 - ball_on
            state["down"] = 1
            state["distance"] = 10
        _advance_clock(state, 10)
        return event

    # RUN or PASS - resolve via player cards
    if play_type == "RUN":
        yards, result = resolve_run(rb, def_team, roll)
    else:
        receiver = _pick_receiver(off_players)
        yards, result = resolve_pass(qb, receiver, def_team, roll)
        event["target"] = receiver.get("name")

    # Special outcomes
    if result == "INT":
        event["turnover"] = True
        event["yards"] = 0
        event["result"] = "INT"
        picker = _pick_defender(def_players)
        event["description"] = f"INTERCEPTED! {picker['name']} picks off {qb['name']}."
        state["possession"] = def_id
        state["ball_on"] = 100 - min(95, ball_on + random.randint(-5, 25))
        state["down"] = 1
        state["distance"] = 10
        _advance_clock(state, 15)
        return event

    if result == "TFL":
        yards = min(yards, -2)
    if result == "SACK":
        sacker = _pick_defender(def_players)
        event["description"] = f"SACK! {sacker['name']} drops {qb['name']} for {abs(yards)} yards."
    if result == "INCOMPLETE":
        event["description"] = f"Pass by {qb['name']} INCOMPLETE."
        yards = 0
    if result == "DROP":
        event["description"] = f"DROP! {event.get('target','Receiver')} can't hang on to {qb['name']}'s pass."
        yards = 0

    # Fumble chance on positive plays
    if result == "NORMAL" and yards > 0 and random.random() < 0.03:
        event["turnover"] = True
        event["result"] = "FUMBLE"
        event["yards"] = yards
        event["description"] = f"FUMBLE! {off_team['name']} lost the ball. {def_team['name']} recovers."
        state["possession"] = def_id
        state["ball_on"] = 100 - (ball_on + yards)
        state["down"] = 1
        state["distance"] = 10
        _advance_clock(state, 8)
        return event

    # apply yards
    new_ball = ball_on + yards
    event["yards"] = yards

    # Description for normal plays
    if not event["description"]:
        if play_type == "RUN":
            desc = f"{rb['name']} runs "
        else:
            rec_name = event.get("target") or "Receiver"
            desc = f"{qb['name']} to {rec_name} "
        if yards >= 20:
            desc += f"for a {yards}-yard "
            desc += "BIG GAIN!" if yards >= 30 else "gain."
        elif yards > 0:
            desc += f"for {yards} yards."
        elif yards == 0:
            desc += "for no gain."
        else:
            desc += f"stuffed for a loss of {abs(yards)}."
        event["description"] = desc
        event["result"] = "BIG_PLAY" if yards >= 20 else "NORMAL"

    # TD?
    if new_ball >= 100:
        event["yards"] = 100 - ball_on
        event["result"] = "TD"
        event["score_change"] = {"team": off_id, "points": 7}  # includes XP assumed good
        event["description"] = f"TOUCHDOWN {off_team['name']}! {event['description']}"
        state[f"{off_id}_score"] += 7
        _score_flip(state, def_id)
        _advance_clock(state, 20)
        return event

    state["ball_on"] = max(1, new_ball)

    # First down?
    if yards >= distance:
        state["down"] = 1
        state["distance"] = 10
    else:
        state["down"] += 1
        state["distance"] = distance - yards
        if state["down"] > 4:
            # turnover on downs
            state["possession"] = def_id
            state["ball_on"] = 100 - state["ball_on"]
            state["down"] = 1
            state["distance"] = 10
            event["description"] += " Turnover on downs."
            event["turnover"] = True

    _advance_clock(state, 25 if play_type == "PASS" else 32)
    return event


def _pick_defender(def_players):
    dfs = [p for p in def_players if p["pos"] not in ("QB", "RB", "WR", "TE", "K")]
    if not dfs:
        return {"name": "Defender"}
    weights = [(p["ovr"] - 65) ** 2 for p in dfs]
    return random.choices(dfs, weights=weights, k=1)[0]


def _score_flip(state, receiving_team):
    """After a score, kickoff to the other team, place them at own 25."""
    state["possession"] = receiving_team
    state["ball_on"] = 25
    state["down"] = 1
    state["distance"] = 10


def _advance_clock(state, seconds: int):
    state["clock"] -= seconds
    if state["clock"] <= 0:
        state["clock"] = 0
        state["quarter"] += 1
        if state["quarter"] <= 4:
            state["clock"] = 900  # 15 min
            if state["quarter"] == 3:
                # halftime kickoff
                state["possession"] = state["halftime_receiver"]
                state["ball_on"] = 25
                state["down"] = 1
                state["distance"] = 10


def new_game_state(home_id: str, away_id: str) -> Dict:
    # coin flip: away receives first, home receives at halftime (traditional)
    first_receiver = away_id
    halftime_receiver = home_id
    return {
        "home": home_id,
        "away": away_id,
        f"{home_id}_score": 0,
        f"{away_id}_score": 0,
        "quarter": 1,
        "clock": 900,
        "down": 1,
        "distance": 10,
        "ball_on": 25,
        "possession": first_receiver,
        "halftime_receiver": halftime_receiver,
        "plays": [],
    }


def simulate_full_game(home_id: str, away_id: str) -> Dict:
    from nfl_data import PLAYERS, TEAMS
    teams = {t["id"]: t for t in TEAMS}
    players = PLAYERS
    state = new_game_state(home_id, away_id)
    plays = []
    stats = {home_id: _empty_stats(), away_id: _empty_stats()}
    max_plays = 200
    while state["quarter"] <= 4 and len(plays) < max_plays:
        ev = simulate_play(state, teams, players)
        plays.append(ev)
        _accumulate_stats(stats, ev, players)
        if state["quarter"] > 4:
            break
    return {
        "home": home_id,
        "away": away_id,
        "home_score": state[f"{home_id}_score"],
        "away_score": state[f"{away_id}_score"],
        "plays": plays,
        "stats": stats,
        "final_state": {k: v for k, v in state.items() if k != "plays"},
    }


def _empty_stats():
    return {
        "total_yards": 0, "pass_yards": 0, "rush_yards": 0,
        "turnovers": 0, "sacks_allowed": 0, "first_downs": 0,
        "plays": 0, "tds": 0, "fgs": 0,
    }


def _accumulate_stats(stats, ev, players):
    off = ev["off"]
    s = stats[off]
    s["plays"] += 1
    if ev["result"] == "SACK":
        s["sacks_allowed"] += 1
        s["pass_yards"] += ev["yards"]
    elif ev["play_type"] == "PASS" and ev["result"] not in ("INT", "INCOMPLETE"):
        s["pass_yards"] += ev["yards"]
    elif ev["play_type"] == "RUN":
        s["rush_yards"] += ev["yards"]
    if ev["result"] not in ("PUNT", "FG_GOOD", "FG_MISS", "INCOMPLETE", "INT"):
        s["total_yards"] += ev["yards"]
    if ev["turnover"]:
        s["turnovers"] += 1
    if ev["result"] == "TD":
        s["tds"] += 1
    if ev["result"] == "FG_GOOD":
        s["fgs"] += 1
    if ev["yards"] >= ev["distance"] and ev["result"] not in ("INT", "FUMBLE"):
        s["first_downs"] += 1
