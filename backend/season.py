"""Season generator and standings logic."""
import random
from typing import List, Dict
from nfl_data import TEAMS


def generate_schedule(season_id: str, weeks: int = 18) -> List[Dict]:
    """Simple round-robin-style schedule. Not real NFL schedule but plausible."""
    teams = [t["id"] for t in TEAMS]
    random.shuffle(teams)
    schedule = []
    pair_counts = {}

    for week in range(1, weeks + 1):
        random.shuffle(teams)
        used = set()
        matchups = []
        for i in range(len(teams)):
            if teams[i] in used:
                continue
            for j in range(i + 1, len(teams)):
                if teams[j] in used:
                    continue
                pair = tuple(sorted([teams[i], teams[j]]))
                if pair_counts.get(pair, 0) >= 2:
                    continue
                home, away = (teams[i], teams[j]) if random.random() < 0.5 else (teams[j], teams[i])
                matchups.append({
                    "week": week,
                    "home": home,
                    "away": away,
                    "played": False,
                    "home_score": None,
                    "away_score": None,
                    "game_id": f"{season_id}-W{week}-{home}-{away}",
                })
                used.add(teams[i])
                used.add(teams[j])
                pair_counts[pair] = pair_counts.get(pair, 0) + 1
                break
        schedule.extend(matchups)
    return schedule


def compute_standings(schedule: List[Dict]) -> List[Dict]:
    team_stats = {t["id"]: {"team": t["id"], "w": 0, "l": 0, "t": 0, "pf": 0, "pa": 0, "div": t["div"], "conf": t["conf"]} for t in TEAMS}
    for g in schedule:
        if not g["played"]:
            continue
        home, away = g["home"], g["away"]
        hs, as_ = g["home_score"], g["away_score"]
        team_stats[home]["pf"] += hs
        team_stats[home]["pa"] += as_
        team_stats[away]["pf"] += as_
        team_stats[away]["pa"] += hs
        if hs > as_:
            team_stats[home]["w"] += 1
            team_stats[away]["l"] += 1
        elif hs < as_:
            team_stats[away]["w"] += 1
            team_stats[home]["l"] += 1
        else:
            team_stats[home]["t"] += 1
            team_stats[away]["t"] += 1
    standings = list(team_stats.values())
    for s in standings:
        games = s["w"] + s["l"] + s["t"]
        s["pct"] = round((s["w"] + s["t"] * 0.5) / games, 3) if games else 0.0
        s["diff"] = s["pf"] - s["pa"]
    standings.sort(key=lambda x: (-x["pct"], -x["diff"], -x["pf"]))
    return standings
