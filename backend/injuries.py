"""Injury system: random per-game injury rolls for key skill players."""
import random
from typing import Dict, List

INJURY_CHANCE = 0.045  # per skill player per game
POSITIONS_AT_RISK = ("QB", "RB", "WR", "TE", "K")

INJURY_DESC = [
    "hamstring strain", "ankle sprain", "shoulder tweak", "knee soreness",
    "concussion protocol", "hip pointer", "ribs contusion", "wrist injury",
    "high ankle sprain", "back tightness",
]


def roll_injuries_for_game(off_players: List[Dict]) -> List[Dict]:
    """Return list of new injuries for starters that played this game."""
    injuries = []
    for p in off_players:
        if not p.get("starter"):
            continue
        if p.get("pos") not in POSITIONS_AT_RISK:
            continue
        if random.random() < INJURY_CHANCE:
            weeks = random.choices([1, 2, 3, 4, 5, 8], weights=[35, 30, 15, 10, 6, 4])[0]
            injuries.append({
                "player": p["name"],
                "pos": p["pos"],
                "weeks_out": weeks,
                "desc": random.choice(INJURY_DESC),
            })
    return injuries


def active_injuries(injuries_list: List[Dict], current_week: int) -> List[Dict]:
    """Return injuries still active at current_week."""
    return [i for i in injuries_list if i["injured_week"] + i["weeks_out"] > current_week]


def injured_names(injuries_list: List[Dict], current_week: int) -> set:
    return {i["player"] for i in active_injuries(injuries_list, current_week)}
