"""Strat-O-Matic style player cards.
Each player has a personal chart indexed by 2D6 roll (2-12).
The chart varies by position and player rating (OVR) so stars produce better outcomes.

For RUN plays: use the RB's card, adjusted by defensive front strength.
For PASS plays: BLEND the QB's card with the target receiver's card (average),
adjusted by defensive backfield strength.
"""
from typing import Dict, List, Tuple
import hashlib


def _seed(name: str) -> int:
    return int(hashlib.md5(name.encode()).hexdigest()[:8], 16)


def _skew(ovr: int) -> float:
    """Rating scaling: 60 -> 0.7, 80 -> 0.95, 99 -> 1.2."""
    return 0.7 + (max(60, min(99, ovr)) - 60) * (0.5 / 39)


def qb_card(player: Dict) -> Dict[int, Dict]:
    """QB card - passing outcomes. Elite QBs miss the sack roll, hit big plays.
    Style hash decides gunslinger vs game-manager profile.
    """
    ovr = player["ovr"]
    s = _skew(ovr)
    style = _seed(player["name"]) % 3  # 0=gunslinger 1=balanced 2=game-manager
    base = {
        2:  {"yards": 0,   "event": "INT"},
        3:  {"yards": -7,  "event": "SACK"},
        4:  {"yards": 0,   "event": "INCOMPLETE"},
        5:  {"yards": 0,   "event": "INCOMPLETE"},
        6:  {"yards": 4,   "event": "NORMAL"},
        7:  {"yards": 6,   "event": "NORMAL"},
        8:  {"yards": 9,   "event": "NORMAL"},
        9:  {"yards": 12,  "event": "NORMAL"},
        10: {"yards": 17,  "event": "NORMAL"},
        11: {"yards": 26,  "event": "BIG_PLAY"},
        12: {"yards": 42,  "event": "DEEP_BOMB"},
    }
    for r, cell in base.items():
        y = cell["yards"]
        if y > 0:
            cell["yards"] = max(1, int(round(y * s)))
        elif y < 0 and r == 3:
            # Elite QBs get sacked less severely
            cell["yards"] = int(round(y / s))
    # Style adjustments
    if style == 0:  # gunslinger: better 11-12, worse 2 (more INTs risk mitigated for ovr)
        base[11]["yards"] = int(base[11]["yards"] * 1.15)
        base[12]["yards"] = int(base[12]["yards"] * 1.15)
        if ovr < 82:
            base[2]["event"] = "INT"  # still INT
    if style == 2:  # game-manager: safer, less big plays
        base[11]["yards"] = int(base[11]["yards"] * 0.85)
        base[12]["yards"] = int(base[12]["yards"] * 0.8)
        if ovr >= 82:
            base[4] = {"yards": 3, "event": "NORMAL"}  # completes short
    return base


def wr_card(player: Dict) -> Dict[int, Dict]:
    """WR/TE card - modifies pass yardage after catch.
    Adds yardage to the QB card outcome for the same roll.
    """
    ovr = player["ovr"]
    s = _skew(ovr)
    # Every roll adds a smaller YAC based on player rating
    return {r: {"yac": max(0, int(round((r - 5) * s * 0.35))), "drop": r == 4 and ovr < 78}
            for r in range(2, 13)}


def rb_card(player: Dict) -> Dict[int, Dict]:
    """RB card - rushing outcomes. Home run hitters vs bruisers."""
    ovr = player["ovr"]
    s = _skew(ovr)
    style = _seed(player["name"]) % 3  # 0=power 1=all-purpose 2=speed
    base = {
        2:  {"yards": -4, "event": "TFL"},
        3:  {"yards": -1, "event": "NORMAL"},
        4:  {"yards": 1,  "event": "NORMAL"},
        5:  {"yards": 2,  "event": "NORMAL"},
        6:  {"yards": 3,  "event": "NORMAL"},
        7:  {"yards": 4,  "event": "NORMAL"},
        8:  {"yards": 6,  "event": "NORMAL"},
        9:  {"yards": 8,  "event": "NORMAL"},
        10: {"yards": 12, "event": "NORMAL"},
        11: {"yards": 20, "event": "BIG_PLAY"},
        12: {"yards": 38, "event": "BREAKAWAY"},
    }
    for r, cell in base.items():
        if cell["yards"] > 0:
            cell["yards"] = max(1, int(round(cell["yards"] * s)))
    if style == 0:  # power: better short yardage, less breakaway
        for r in (2, 3, 4):
            base[r]["yards"] += 1
        base[12]["yards"] = int(base[12]["yards"] * 0.8)
    if style == 2:  # speed: worse short, huge home runs
        base[3]["yards"] -= 1
        base[11]["yards"] = int(base[11]["yards"] * 1.2)
        base[12]["yards"] = int(base[12]["yards"] * 1.3)
    return base


def def_modifier(def_team: Dict, play_type: str) -> int:
    """Yardage modifier applied to card yardage based on team defense rating."""
    base = def_team["def"]
    return -round((base - 78) / 10)  # good defenses reduce yardage


def resolve_pass(qb: Dict, wr: Dict, def_team: Dict, roll: int) -> Tuple[int, str]:
    """Return (yards, event) for a pass play."""
    qbc = qb_card(qb)
    wrc = wr_card(wr)
    cell = qbc[roll]
    yards = cell["yards"]
    event = cell["event"]
    # Add YAC for completed positive plays
    if event in ("NORMAL", "BIG_PLAY", "DEEP_BOMB") and yards > 0:
        yards += wrc[roll]["yac"]
    # Drop check
    if event == "NORMAL" and wrc[roll].get("drop"):
        return 0, "DROP"
    # Apply defensive modifier
    yards += def_modifier(def_team, "PASS")
    return yards, event


def resolve_run(rb: Dict, def_team: Dict, roll: int) -> Tuple[int, str]:
    rc = rb_card(rb)
    cell = rc[roll]
    yards = cell["yards"] + def_modifier(def_team, "RUN")
    return yards, cell["event"]


def full_card(player: Dict) -> Dict:
    """Return a serialisable full card for UI viewing."""
    pos = player["pos"]
    if pos == "QB":
        return {"type": "QB", "chart": qb_card(player)}
    if pos == "RB":
        return {"type": "RB", "chart": rb_card(player)}
    if pos in ("WR", "TE"):
        return {"type": pos, "chart": wr_card(player)}
    # Simplistic defender card
    ovr = player["ovr"]
    s = _skew(ovr)
    return {"type": "DEF", "chart": {
        r: {"impact": max(0, int(round((r - 5) * s)))} for r in range(2, 13)
    }}
