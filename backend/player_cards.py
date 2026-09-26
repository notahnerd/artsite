"""Strat-O-Matic style player + team defense cards (3D6 chart).

Each play:
  * Roll 3 white dice -> sum 3-18 (chart index)
  * Roll 1 red die:
      1/2/3 = read the OFFENSE card (QB/RB/WR-TE)
      4/5/6 = read the DEFENSE team card

Offense cards produce yardage + result from the player's own strengths.
Defense cards produce defensive outcomes (SACK/INT/TFL/hurry/coverage).
"""
from typing import Dict, Tuple
import hashlib


def _seed(name: str) -> int:
    return int(hashlib.md5(name.encode()).hexdigest()[:8], 16)


def _skew(ovr: int) -> float:
    """60 -> 0.7, 80 -> 0.95, 99 -> 1.2"""
    return 0.7 + (max(60, min(99, ovr)) - 60) * (0.5 / 39)


# ---------- QB (Offense read) ----------
def qb_card(player: Dict) -> Dict[int, Dict]:
    ovr = player["ovr"]
    s = _skew(ovr)
    style = _seed(player["name"]) % 3  # 0=gunslinger 1=balanced 2=game-manager
    # 3D6 is bell-shaped; center = 10-11. Star QBs shine in the middle+top.
    base = {
        3:  {"yards": 0,   "event": "INT"},
        4:  {"yards": 0,   "event": "INT"},
        5:  {"yards": -8,  "event": "SACK"},
        6:  {"yards": 0,   "event": "INCOMPLETE"},
        7:  {"yards": 0,   "event": "INCOMPLETE"},
        8:  {"yards": 3,   "event": "NORMAL"},
        9:  {"yards": 5,   "event": "NORMAL"},
        10: {"yards": 7,   "event": "NORMAL"},
        11: {"yards": 9,   "event": "NORMAL"},
        12: {"yards": 12,  "event": "NORMAL"},
        13: {"yards": 15,  "event": "NORMAL"},
        14: {"yards": 20,  "event": "NORMAL"},
        15: {"yards": 26,  "event": "BIG_PLAY"},
        16: {"yards": 34,  "event": "BIG_PLAY"},
        17: {"yards": 48,  "event": "DEEP_BOMB"},
        18: {"yards": 65,  "event": "DEEP_BOMB"},
    }
    for r, cell in base.items():
        y = cell["yards"]
        if y > 0:
            cell["yards"] = max(1, int(round(y * s)))
        elif y < 0 and r == 5:
            cell["yards"] = int(round(y / s))
    if style == 0:  # gunslinger: bigger boom, slightly more INT
        for r in (15, 16, 17, 18):
            base[r]["yards"] = int(base[r]["yards"] * 1.15)
    if style == 2:  # game-manager: safer short throws, muted deep shots
        base[6] = {"yards": 2, "event": "NORMAL"}
        base[7] = {"yards": 4, "event": "NORMAL"}
        for r in (17, 18):
            base[r]["yards"] = int(base[r]["yards"] * 0.8)
    return base


# ---------- RB (Offense read) ----------
def rb_card(player: Dict) -> Dict[int, Dict]:
    ovr = player["ovr"]
    s = _skew(ovr)
    style = _seed(player["name"]) % 3  # 0=power 1=all-purpose 2=speed
    base = {
        3:  {"yards": -5, "event": "TFL"},
        4:  {"yards": -3, "event": "TFL"},
        5:  {"yards": -1, "event": "NORMAL"},
        6:  {"yards": 0,  "event": "NORMAL"},
        7:  {"yards": 1,  "event": "NORMAL"},
        8:  {"yards": 2,  "event": "NORMAL"},
        9:  {"yards": 3,  "event": "NORMAL"},
        10: {"yards": 4,  "event": "NORMAL"},
        11: {"yards": 5,  "event": "NORMAL"},
        12: {"yards": 7,  "event": "NORMAL"},
        13: {"yards": 9,  "event": "NORMAL"},
        14: {"yards": 12, "event": "NORMAL"},
        15: {"yards": 16, "event": "BIG_PLAY"},
        16: {"yards": 22, "event": "BIG_PLAY"},
        17: {"yards": 32, "event": "BREAKAWAY"},
        18: {"yards": 55, "event": "BREAKAWAY"},
    }
    for r, cell in base.items():
        if cell["yards"] > 0:
            cell["yards"] = max(1, int(round(cell["yards"] * s)))
    if style == 0:  # power
        for r in (5, 6, 7, 8):
            base[r]["yards"] += 1
        base[17]["yards"] = int(base[17]["yards"] * 0.8)
        base[18]["yards"] = int(base[18]["yards"] * 0.8)
    if style == 2:  # speed
        base[6]["yards"] -= 1
        base[15]["yards"] = int(base[15]["yards"] * 1.2)
        base[17]["yards"] = int(base[17]["yards"] * 1.25)
        base[18]["yards"] = int(base[18]["yards"] * 1.3)
    return base


# ---------- WR/TE (Offense - YAC blend into QB card) ----------
def wr_card(player: Dict) -> Dict[int, Dict]:
    ovr = player["ovr"]
    s = _skew(ovr)
    return {
        r: {
            "yac": max(0, int(round((r - 8) * s * 0.6))),
            "drop": r == 6 and ovr < 78,
        }
        for r in range(3, 19)
    }


# ---------- Team Defense (DEF read) ----------
def team_pass_defense_card(team: Dict) -> Dict[int, Dict]:
    d = team["def"]
    s = _skew(d)
    # Defense read means the DEFENSE made a play. High roll = big defensive play.
    base = {
        3:  {"yards": 22,  "event": "COVERAGE_BUST"},
        4:  {"yards": 15,  "event": "COVERAGE_BUST"},
        5:  {"yards": 11,  "event": "NORMAL"},
        6:  {"yards": 8,   "event": "NORMAL"},
        7:  {"yards": 6,   "event": "NORMAL"},
        8:  {"yards": 5,   "event": "NORMAL"},
        9:  {"yards": 4,   "event": "NORMAL"},
        10: {"yards": 3,   "event": "NORMAL"},
        11: {"yards": 0,   "event": "INCOMPLETE"},
        12: {"yards": 0,   "event": "INCOMPLETE"},
        13: {"yards": 0,   "event": "INCOMPLETE"},
        14: {"yards": -3,  "event": "HURRY"},
        15: {"yards": -7,  "event": "SACK"},
        16: {"yards": -9,  "event": "SACK"},
        17: {"yards": 0,   "event": "INT"},
        18: {"yards": -12, "event": "INT_RETURN_TD"},
    }
    # Scale big defensive results by defense skew
    for r in (14, 15, 16, 17, 18):
        base[r]["yards"] = int(round(base[r]["yards"] * s))
    # Weaker defenses give up more on coverage bust
    for r in (3, 4):
        base[r]["yards"] = int(round(base[r]["yards"] * (2.0 - s)))
    return base


def team_run_defense_card(team: Dict) -> Dict[int, Dict]:
    d = team["def"]
    s = _skew(d)
    base = {
        3:  {"yards": 30,  "event": "BREAKAWAY"},
        4:  {"yards": 18,  "event": "BIG_PLAY"},
        5:  {"yards": 10,  "event": "NORMAL"},
        6:  {"yards": 7,   "event": "NORMAL"},
        7:  {"yards": 5,   "event": "NORMAL"},
        8:  {"yards": 4,   "event": "NORMAL"},
        9:  {"yards": 3,   "event": "NORMAL"},
        10: {"yards": 2,   "event": "NORMAL"},
        11: {"yards": 1,   "event": "NORMAL"},
        12: {"yards": 0,   "event": "NORMAL"},
        13: {"yards": -1,  "event": "NORMAL"},
        14: {"yards": -3,  "event": "TFL"},
        15: {"yards": -4,  "event": "STUFF"},
        16: {"yards": -2,  "event": "STUFF"},
        17: {"yards": -1,  "event": "FUMBLE"},
        18: {"yards": 0,   "event": "FUMBLE"},
    }
    for r in (12, 13, 14, 15, 16, 17, 18):
        base[r]["yards"] = int(round(base[r]["yards"] * s))
    for r in (3, 4):
        base[r]["yards"] = int(round(base[r]["yards"] * (2.0 - s)))
    return base


# ---------- Resolution ----------
def resolve_pass(qb: Dict, wr: Dict, def_team: Dict, roll_sum: int, read: str) -> Tuple[int, str]:
    """read: 'OFF' or 'DEF'. Returns (yards, event)."""
    if read == "DEF":
        cell = team_pass_defense_card(def_team)[roll_sum]
        return cell["yards"], cell["event"]
    # OFF read
    qbc = qb_card(qb)
    wrc = wr_card(wr)
    cell = qbc[roll_sum]
    yards = cell["yards"]
    event = cell["event"]
    if event in ("NORMAL", "BIG_PLAY", "DEEP_BOMB") and yards > 0:
        yards += wrc[roll_sum]["yac"]
    if event == "NORMAL" and wrc[roll_sum].get("drop"):
        return 0, "DROP"
    return yards, event


def resolve_run(rb: Dict, def_team: Dict, roll_sum: int, read: str) -> Tuple[int, str]:
    if read == "DEF":
        cell = team_run_defense_card(def_team)[roll_sum]
        return cell["yards"], cell["event"]
    cell = rb_card(rb)[roll_sum]
    return cell["yards"], cell["event"]


# ---------- Kicker (Offense read) ----------
def k_card(player: Dict) -> Dict[int, Dict]:
    """Kicker accuracy card. Reading is 'HIT' (make), 'MISS', or 'BOMB' (great kick).
    range_bonus tells how much extra distance the kicker can hit on that roll.
    """
    ovr = player["ovr"]
    s = _skew(ovr)
    max_range = 40 + int(round((ovr - 60) / 3))  # 60 OVR => 40 yd, 90 OVR => 50 yd, 99 => 53
    return {
        3:  {"result": "WIDE_LEFT",  "range_bonus": 0,           "make": False},
        4:  {"result": "WIDE_RIGHT", "range_bonus": 0,           "make": False},
        5:  {"result": "HOOK",       "range_bonus": -8,          "make": None},
        6:  {"result": "SLICE",      "range_bonus": -4,          "make": None},
        7:  {"result": "STRAIGHT",   "range_bonus": 0,           "make": None},
        8:  {"result": "STRAIGHT",   "range_bonus": 1,           "make": None},
        9:  {"result": "STRAIGHT",   "range_bonus": 3,           "make": None},
        10: {"result": "STRAIGHT",   "range_bonus": 5,           "make": None},
        11: {"result": "STRAIGHT",   "range_bonus": 6,           "make": None},
        12: {"result": "STRAIGHT",   "range_bonus": 7,           "make": None},
        13: {"result": "STRAIGHT",   "range_bonus": 8,           "make": None},
        14: {"result": "PURE",       "range_bonus": 10,          "make": None},
        15: {"result": "PURE",       "range_bonus": 12,          "make": None},
        16: {"result": "BOMB",       "range_bonus": 15,          "make": None},
        17: {"result": "BOMB",       "range_bonus": 18,          "make": None},
        18: {"result": "BOOMSTICK",  "range_bonus": 25,          "make": True},
    }, max_range


def full_card(player: Dict) -> Dict:
    pos = player["pos"]
    if pos == "QB":
        return {"type": "QB", "chart": qb_card(player)}
    if pos == "RB":
        return {"type": "RB", "chart": rb_card(player)}
    if pos in ("WR", "TE"):
        return {"type": pos, "chart": wr_card(player)}
    if pos == "K":
        chart, max_range = k_card(player)
        return {"type": "K", "chart": chart, "max_range": max_range}
    ovr = player["ovr"]
    s = _skew(ovr)
    return {"type": "DEF", "chart": {
        r: {"impact": max(0, int(round((r - 7) * s)))} for r in range(3, 19)
    }}
