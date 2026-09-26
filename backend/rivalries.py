"""Team rivalries - division rivals are auto-rivals. Users can add a picked rival.
Rivalry games get a +1 chart bump to both teams (more big plays, more intensity).
"""
from typing import List, Dict
from nfl_data import TEAMS


def division_rivals(team_id: str) -> List[str]:
    t = next((x for x in TEAMS if x["id"] == team_id), None)
    if not t:
        return []
    return [x["id"] for x in TEAMS if x["conf"] == t["conf"] and x["div"] == t["div"] and x["id"] != team_id]


# Classic historical rivalries beyond division
LEGACY_RIVALRIES = {
    ("KC", "LV"), ("KC", "DEN"),
    ("BAL", "PIT"),
    ("DAL", "SF"), ("DAL", "PHI"),
    ("GB", "CHI"),
    ("NE", "NYJ"),
    ("MIA", "BUF"),
    ("PHI", "NYG"),
    ("SEA", "SF"),
    ("LAR", "SF"),
}


def _pair(a: str, b: str) -> tuple:
    return tuple(sorted([a, b]))


def is_rivalry(a: str, b: str, extra_rival_for: Dict[str, str] = None) -> bool:
    if b in division_rivals(a):
        return True
    if _pair(a, b) in {_pair(x, y) for (x, y) in LEGACY_RIVALRIES}:
        return True
    extra_rival_for = extra_rival_for or {}
    if extra_rival_for.get(a) == b or extra_rival_for.get(b) == a:
        return True
    return False


def annotate_schedule(schedule: List[Dict], extra_rival_for: Dict[str, str] = None):
    for g in schedule:
        g["rivalry"] = is_rivalry(g["home"], g["away"], extra_rival_for)
