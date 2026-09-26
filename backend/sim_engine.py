"""Tabletop NFL simulation engine (Strat-O-Matic style).
Each play resolves through PLAYER CARDS (see player_cards.py):
- Pass: QB card blended with target WR/TE card; team DEF adjusts
- Run: RB card; team DEF adjusts
- Weather modifies pass yardage, INT/big-play chance and kicking
- Depth chart overrides QB/RB/K if provided
- Injuries: names in `injured` set are removed from consideration
- Difficulty: yardage multiplier (arcade=1.0, balanced=0.75, realistic=0.55)
"""
import random
from typing import Dict, List, Tuple, Optional

from nfl_data import get_team, get_players
from player_cards import resolve_pass, resolve_run
from weather import WEATHER_TYPES
from injuries import roll_injuries_for_game


DIFFICULTY_MULT = {"arcade": 1.0, "balanced": 0.7, "realistic": 0.5}


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


def choose_play_type(down, distance, ball_on, score_diff, quarter, clock):
    yards_to_goal = 100 - ball_on
    if down == 4:
        if yards_to_goal <= 35:
            return "FG"
        if distance <= 2 and ball_on > 55:
            return "RUN" if random.random() < 0.5 else "PASS"
        return "PUNT"
    pass_prob = 0.55
    if score_diff < -7 and quarter >= 3:
        pass_prob = 0.80
    if distance >= 8:
        pass_prob = min(0.85, pass_prob + 0.15)
    if distance <= 2:
        pass_prob = 0.35
    return "PASS" if random.random() < pass_prob else "RUN"


def _pick_receiver(players: List[Dict]) -> Dict:
    pool = [p for p in players if p.get("starter") and p["pos"] in ("WR", "TE", "RB")]
    if not pool:
        pool = [p for p in players if p["pos"] in ("WR", "TE", "RB")]
    if not pool:
        return {"name": "Receiver"}
    weights = [(p["ovr"] - 65) ** 2 for p in pool]
    return random.choices(pool, weights=weights, k=1)[0]


def _pick_defender(def_players):
    dfs = [p for p in def_players if p["pos"] not in ("QB", "RB", "WR", "TE", "K")]
    if not dfs:
        return {"name": "Defender"}
    weights = [(p["ovr"] - 65) ** 2 for p in dfs]
    return random.choices(dfs, weights=weights, k=1)[0]


def _apply_weather_pass(yards: int, result: str, weather_code: str) -> Tuple[int, str]:
    w = WEATHER_TYPES.get(weather_code, WEATHER_TYPES["CLEAR"])
    # Scale yardage
    if yards > 0:
        mult = w["pass_mult"]
        if result in ("BIG_PLAY", "DEEP_BOMB"):
            mult *= w["big_play_mult"]
        yards = max(0, int(round(yards * mult)))
    # INT bonus on 2/3 roll
    if w["int_bonus"] > 0 and result == "SACK" and random.random() < 0.25:
        result = "INT"
    if w["int_bonus"] < 0 and result == "INT" and random.random() < 0.35:
        result = "INCOMPLETE"
    return yards, result


def _add_player_stat(pstats, player, key, val):
    if not player or not player.get("name"):
        return
    entry = pstats.setdefault(player["name"], {
        "name": player["name"],
        "team": player.get("team", ""),
        "pos": player.get("pos", ""),
        "pass_yds": 0, "pass_td": 0, "int": 0,
        "rush_yds": 0, "rush_td": 0, "carries": 0,
        "rec": 0, "rec_yds": 0, "rec_td": 0, "targets": 0,
        "fgm": 0, "fga": 0, "sacks": 0, "picks": 0,
    })
    entry[key] = entry.get(key, 0) + val


def simulate_play(state, teams, players, weather_code, pstats, difficulty_mult=1.0):
    off_id = state["possession"]
    def_id = state["home"] if off_id == state["away"] else state["away"]
    off_team = teams[off_id]
    def_team = teams[def_id]
    off_players = players[off_id]
    def_players = players[def_id]

    down, distance, ball_on = state["down"], state["distance"], state["ball_on"]
    score_diff = state[f"{off_id}_score"] - state[f"{def_id}_score"]
    play_type = choose_play_type(down, distance, ball_on, score_diff, state["quarter"], state["clock"])

    d1, d2 = roll_2d6()
    roll = d1 + d2

    # Pick starters
    qb = next((p for p in off_players if p.get("starter") and p["pos"] == "QB"), {"name": "QB", "ovr": 75})
    rb = next((p for p in off_players if p.get("starter") and p["pos"] == "RB"), {"name": "RB", "ovr": 75})
    kicker = next((p for p in off_players if p.get("starter") and p["pos"] == "K"), {"name": "K", "ovr": 80})

    event = {
        "quarter": state["quarter"], "clock": state["clock"],
        "off": off_id, "def": def_id,
        "down": down, "distance": distance, "ball_on": ball_on,
        "play_type": play_type, "dice": [d1, d2], "roll": roll,
        "yards": 0, "result": "NORMAL", "description": "",
        "score_change": None, "turnover": False,
    }

    if play_type == "PUNT":
        punt_dist = random.randint(38, 55)
        new_ball = max(20, min(95, ball_on + punt_dist))
        new_ball_flipped = 100 - 75 if new_ball >= 100 else 100 - new_ball
        event["yards"] = punt_dist
        event["result"] = "PUNT"
        event["description"] = f"{off_team['name']} punt {punt_dist} yards."
        state["possession"] = def_id
        state["ball_on"] = new_ball_flipped
        state["down"] = 1
        state["distance"] = 10
        _advance_clock(state, 12)
        return event

    if play_type == "FG":
        yards_to_goal = 100 - ball_on
        fg_distance = yards_to_goal + 17
        base = max(0.35, (kicker.get("ovr", 80) - 60) / 40)
        if fg_distance <= 35: chance = base + 0.35
        elif fg_distance <= 45: chance = base + 0.15
        elif fg_distance <= 55: chance = base - 0.05
        else: chance = base - 0.25
        # weather kicking penalty (penalty in %)
        chance -= WEATHER_TYPES.get(weather_code, WEATHER_TYPES["CLEAR"])["fg_penalty"] / 100.0
        chance = max(0.12, min(0.98, chance))
        success = random.random() < chance
        event["yards"] = fg_distance
        _add_player_stat(pstats, kicker, "fga", 1)
        if success:
            event["result"] = "FG_GOOD"
            event["score_change"] = {"team": off_id, "points": 3}
            event["description"] = f"{kicker['name']} nails a {fg_distance}-yard field goal! GOOD."
            state[f"{off_id}_score"] += 3
            _add_player_stat(pstats, kicker, "fgm", 1)
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

    if play_type == "RUN":
        yards, result = resolve_run(rb, def_team, roll)
        if yards > 0:
            yards = int(round(yards * difficulty_mult))
        _add_player_stat(pstats, rb, "carries", 1)
    else:
        receiver = _pick_receiver(off_players)
        yards, result = resolve_pass(qb, receiver, def_team, roll)
        yards, result = _apply_weather_pass(yards, result, weather_code)
        if yards > 0:
            yards = int(round(yards * difficulty_mult))
        event["target"] = receiver.get("name")
        _add_player_stat(pstats, receiver, "targets", 1)

    if result == "INT":
        event["turnover"] = True
        event["yards"] = 0
        event["result"] = "INT"
        picker = _pick_defender(def_players)
        event["description"] = f"INTERCEPTED! {picker['name']} picks off {qb['name']}."
        _add_player_stat(pstats, qb, "int", 1)
        _add_player_stat(pstats, picker, "picks", 1)
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
        _add_player_stat(pstats, sacker, "sacks", 1)
    if result == "INCOMPLETE":
        event["description"] = f"Pass by {qb['name']} INCOMPLETE."
        yards = 0
    if result == "DROP":
        event["description"] = f"DROP! {event.get('target','Receiver')} can't hang on to {qb['name']}'s pass."
        yards = 0

    # Fumble chance
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

    new_ball = ball_on + yards
    event["yards"] = yards

    # Passing/receiving stats accrual
    if play_type == "PASS" and result in ("NORMAL", "BIG_PLAY", "DEEP_BOMB") and yards > 0:
        _add_player_stat(pstats, qb, "pass_yds", yards)
        rec_name = event.get("target")
        rec = next((p for p in off_players if p["name"] == rec_name), None)
        _add_player_stat(pstats, rec, "rec", 1)
        _add_player_stat(pstats, rec, "rec_yds", yards)
    if play_type == "RUN" and yards > 0:
        _add_player_stat(pstats, rb, "rush_yds", yards)

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

    if new_ball >= 100:
        event["yards"] = 100 - ball_on
        event["result"] = "TD"
        event["score_change"] = {"team": off_id, "points": 7}
        event["description"] = f"TOUCHDOWN {off_team['name']}! {event['description']}"
        # Attribute TD
        if play_type == "PASS":
            _add_player_stat(pstats, qb, "pass_td", 1)
            rec_name = event.get("target")
            rec = next((p for p in off_players if p["name"] == rec_name), None)
            _add_player_stat(pstats, rec, "rec_td", 1)
        else:
            _add_player_stat(pstats, rb, "rush_td", 1)
        state[f"{off_id}_score"] += 7
        _score_flip(state, def_id)
        _advance_clock(state, 20)
        return event

    state["ball_on"] = max(1, new_ball)
    if yards >= distance:
        state["down"] = 1
        state["distance"] = 10
    else:
        state["down"] += 1
        state["distance"] = distance - yards
        if state["down"] > 4:
            state["possession"] = def_id
            state["ball_on"] = 100 - state["ball_on"]
            state["down"] = 1
            state["distance"] = 10
            event["description"] += " Turnover on downs."
            event["turnover"] = True

    _advance_clock(state, 25 if play_type == "PASS" else 32)
    return event


def _score_flip(state, receiving_team):
    state["possession"] = receiving_team
    state["ball_on"] = 25
    state["down"] = 1
    state["distance"] = 10


def _advance_clock(state, seconds):
    state["clock"] -= seconds
    if state["clock"] <= 0:
        state["clock"] = 0
        state["quarter"] += 1
        if state["quarter"] <= 4:
            state["clock"] = 900
            if state["quarter"] == 3:
                state["possession"] = state["halftime_receiver"]
                state["ball_on"] = 25
                state["down"] = 1
                state["distance"] = 10


def new_game_state(home_id, away_id):
    return {
        "home": home_id, "away": away_id,
        f"{home_id}_score": 0, f"{away_id}_score": 0,
        "quarter": 1, "clock": 900,
        "down": 1, "distance": 10, "ball_on": 25,
        "possession": away_id, "halftime_receiver": home_id,
        "plays": [],
    }


def _apply_starters_override(all_players: List[Dict], depth_chart: Optional[Dict], injured_names: Optional[set] = None) -> List[Dict]:
    """Depth chart is {pos: player_name}. Injuries force fallback to backup at that pos."""
    injured_names = injured_names or set()
    result = [dict(p) for p in all_players]

    # Apply user depth chart first
    if depth_chart:
        for pos, name in depth_chart.items():
            for p in result:
                if p["pos"] == pos:
                    p["starter"] = False
            for p in result:
                if p["name"] == name:
                    p["starter"] = True

    # Enforce injuries: if any starter is injured, promote first healthy backup at that pos
    positions_needing = set()
    for p in result:
        if p["name"] in injured_names:
            p["starter"] = False
            p["injured"] = True
            positions_needing.add(p["pos"])
    for pos in positions_needing:
        healthy = [p for p in result if p["pos"] == pos and p["name"] not in injured_names]
        if not healthy:
            continue
        if any(p.get("starter") for p in healthy):
            continue
        healthy.sort(key=lambda x: -x["ovr"])
        healthy[0]["starter"] = True
    return result


def simulate_full_game(home_id, away_id, weather_code="CLEAR", depth_charts=None,
                       injuries=None, difficulty="arcade", allow_ot=True,
                       roll_new_injuries=True, custom_rosters=None) -> Dict:
    from nfl_data import TEAMS
    teams = {t["id"]: t for t in TEAMS}
    depth_charts = depth_charts or {}
    injuries = injuries or {}
    custom_rosters = custom_rosters or {}
    mult = DIFFICULTY_MULT.get(difficulty, 1.0)

    def _roster(tid):
        return custom_rosters.get(tid) or get_players(tid)

    players = {
        home_id: _apply_starters_override(_roster(home_id), depth_charts.get(home_id), injuries.get(home_id)),
        away_id: _apply_starters_override(_roster(away_id), depth_charts.get(away_id), injuries.get(away_id)),
    }
    for tid, plist in players.items():
        for p in plist:
            p["team"] = tid

    state = new_game_state(home_id, away_id)
    plays = []
    stats = {home_id: _empty_stats(), away_id: _empty_stats()}
    pstats: Dict[str, Dict] = {}
    max_plays = 220

    while state["quarter"] <= 4 and len(plays) < max_plays:
        ev = simulate_play(state, teams, players, weather_code, pstats, mult)
        plays.append(ev)
        _accumulate_stats(stats, ev)

    hs = state[f"{home_id}_score"]
    as_ = state[f"{away_id}_score"]
    if allow_ot and hs == as_:
        state["clock"] = 600
        state["quarter"] = 5
        ot_plays = 0
        while ot_plays < 40:
            ev = simulate_play(state, teams, players, weather_code, pstats, mult)
            plays.append(ev)
            _accumulate_stats(stats, ev)
            ot_plays += 1
            if state[f"{home_id}_score"] != state[f"{away_id}_score"]:
                break
        if state[f"{home_id}_score"] == state[f"{away_id}_score"]:
            if stats[home_id]["total_yards"] >= stats[away_id]["total_yards"]:
                state[f"{home_id}_score"] += 3
            else:
                state[f"{away_id}_score"] += 3

    # Roll new injuries after the game for starters who actually played
    new_injuries = {}
    if roll_new_injuries:
        new_injuries[home_id] = roll_injuries_for_game(players[home_id])
        new_injuries[away_id] = roll_injuries_for_game(players[away_id])

    return {
        "home": home_id, "away": away_id,
        "home_score": state[f"{home_id}_score"],
        "away_score": state[f"{away_id}_score"],
        "plays": plays, "stats": stats,
        "player_stats": pstats,
        "weather": weather_code,
        "difficulty": difficulty,
        "new_injuries": new_injuries,
    }


def _empty_stats():
    return {
        "total_yards": 0, "pass_yards": 0, "rush_yards": 0,
        "turnovers": 0, "sacks_allowed": 0, "first_downs": 0,
        "plays": 0, "tds": 0, "fgs": 0,
    }


def _accumulate_stats(stats, ev):
    off = ev["off"]
    s = stats[off]
    s["plays"] += 1
    if ev["result"] == "SACK":
        s["sacks_allowed"] += 1
        s["pass_yards"] += ev["yards"]
    elif ev["play_type"] == "PASS" and ev["result"] not in ("INT", "INCOMPLETE", "DROP"):
        s["pass_yards"] += ev["yards"]
    elif ev["play_type"] == "RUN":
        s["rush_yards"] += ev["yards"]
    if ev["result"] not in ("PUNT", "FG_GOOD", "FG_MISS", "INCOMPLETE", "INT", "DROP"):
        s["total_yards"] += ev["yards"]
    if ev["turnover"]:
        s["turnovers"] += 1
    if ev["result"] == "TD":
        s["tds"] += 1
    if ev["result"] == "FG_GOOD":
        s["fgs"] += 1
    if ev["yards"] >= ev["distance"] and ev["result"] not in ("INT", "FUMBLE"):
        s["first_downs"] += 1
