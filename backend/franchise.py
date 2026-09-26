"""Franchise mode: roll seasons forward with player aging, retirements, and draft classes."""
import random
import hashlib
from typing import Dict, List
from nfl_data import PLAYERS, TEAMS

RETIREMENT_AGE_START = 33  # players "age" abstractly — use OVR + name hash

ROOKIE_FIRST_NAMES = [
    "Tyler", "Jayden", "Marcus", "Trey", "DeAndre", "Malik", "Kyle", "Isaiah",
    "Xavier", "Devin", "Jalen", "Cody", "Trevon", "Trevor", "Micah", "Chase",
    "Justin", "Jordan", "Cam", "Andre", "Blake", "Ethan", "Damon", "Brock",
]
ROOKIE_LAST_NAMES = [
    "Reed", "Harris", "Miller", "Thompson", "Jackson", "Martin", "Hayes",
    "Bennett", "Foster", "Hughes", "Grant", "Brooks", "Owens", "Rivers",
    "Sanders", "Coleman", "Hall", "Watkins", "Morris", "Simmons",
]

POSITIONS_ROOKIES = ["QB", "RB", "WR", "WR", "TE", "K"]


def _age_hash(name: str) -> int:
    return int(hashlib.md5(name.encode()).hexdigest()[:6], 16) % 15


def age_and_retire(team_players: List[Dict]) -> Dict:
    """Age players by 1 year. Returns dict with new roster and retired list."""
    retired = []
    survivors = []
    for p in team_players:
        # abstract age: base 24 + hash offset; each roll_year adds 1
        age = p.get("age", 24 + _age_hash(p["name"]))
        age += 1
        # Retirement chance: OVR decay + age
        retire_chance = 0.0
        if age >= 33: retire_chance += 0.15
        if age >= 35: retire_chance += 0.25
        if age >= 37: retire_chance += 0.4
        if p.get("ovr", 75) < 72 and age >= 32:
            retire_chance += 0.25
        if random.random() < retire_chance:
            retired.append({**p, "age": age})
            continue
        # OVR aging: young players get better, older decline
        ovr = p.get("ovr", 75)
        if age <= 25:
            ovr = min(99, ovr + random.choice([0, 1, 1, 2]))
        elif age <= 29:
            ovr = min(99, ovr + random.choice([-1, 0, 0, 1]))
        elif age <= 32:
            ovr = max(60, ovr + random.choice([-2, -1, 0]))
        else:
            ovr = max(60, ovr + random.choice([-3, -2, -1]))
        survivors.append({**p, "age": age, "ovr": ovr})
    return {"roster": survivors, "retired": retired}


def generate_rookies(team_id: str, count: int = 3) -> List[Dict]:
    seed = sum(ord(c) for c in team_id) + random.randint(0, 999)
    rookies = []
    positions = random.sample(POSITIONS_ROOKIES, min(count, len(POSITIONS_ROOKIES)))
    for i, pos in enumerate(positions):
        fn = ROOKIE_FIRST_NAMES[(seed + i * 3) % len(ROOKIE_FIRST_NAMES)]
        ln = ROOKIE_LAST_NAMES[(seed + i * 5 + 7) % len(ROOKIE_LAST_NAMES)]
        base = 68 + random.randint(0, 22)  # 68-90
        rookies.append({
            "name": f"{fn} {ln}",
            "pos": pos,
            "ovr": base,
            "num": 10 + (seed + i) % 80,
            "age": 22,
            "rookie": True,
        })
    return rookies


def roll_franchise_year(season_doc: Dict) -> Dict:
    """Generate the next-year data: retirements + rookies per team."""
    changes = {}
    for team in TEAMS:
        tid = team["id"]
        # Start from current roster (starters from PLAYERS + any prior franchise updates)
        base = season_doc.get("franchise_rosters", {}).get(tid) or PLAYERS.get(tid, [])
        aged = age_and_retire(base)
        rookies = generate_rookies(tid, count=random.choice([2, 3, 3, 4]))
        new_roster = aged["roster"] + rookies
        changes[tid] = {
            "roster": new_roster,
            "retired": aged["retired"],
            "rookies": rookies,
        }
    return changes
