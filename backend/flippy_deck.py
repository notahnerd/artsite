"""Flippy Deck — a 250-card pre-play deck that either resolves the play
directly (100 result cards) or delegates to the dice engine (150 dice cards).

Composition (exactly 250):
- 150 DICE cards        → "Roll the dice" (default flow)
- 98  YARDS cards       → override play yards; range -6 .. +45
- 2   INJURY cards      → concussion, random offensive starter out for game;
                           40% chance it is the QB

The yardage curve is realistic-ish (right-skewed with rare breakaways).
"""
from __future__ import annotations
import random
from typing import Dict, List, Optional


# Realistic-ish curve for the 98 yardage cards.
# Sum(counts) must equal 98.
_YARDAGE_CURVE = [
    (-6, 1), (-5, 1), (-4, 2), (-3, 3), (-2, 4), (-1, 5),
    (0, 6),  (1, 8),  (2, 9),  (3, 10), (4, 9),  (5, 7),
    (6, 6),  (7, 5),  (8, 4),  (9, 3),  (10, 3),
    (11, 2), (12, 2), (13, 1), (14, 1), (15, 1),
    (18, 1), (22, 1), (28, 1), (45, 2),
]

DECK_SIZE = 250
NUM_DICE = 150
NUM_INJURY = 2


def _build_cards() -> List[Dict]:
    cards: List[Dict] = []
    for _ in range(NUM_DICE):
        cards.append({"type": "DICE"})
    for yards, count in _YARDAGE_CURVE:
        for _ in range(count):
            cards.append({"type": "YARDS", "yards": yards})
    for _ in range(NUM_INJURY):
        cards.append({"type": "INJURY"})
    assert len(cards) == DECK_SIZE, f"deck size {len(cards)} != {DECK_SIZE}"
    return cards


def new_deck() -> Dict:
    """Return a fresh shuffled deck state."""
    cards = _build_cards()
    random.shuffle(cards)
    return {
        "cards": cards,
        "drawn": 0,
        "reshuffles": 0,
    }


def draw(deck: Dict) -> Dict:
    """Pop the next card. Auto-reshuffle when empty."""
    if not deck["cards"]:
        deck["cards"] = _build_cards()
        random.shuffle(deck["cards"])
        deck["reshuffles"] += 1
        deck["drawn"] = 0
    card = deck["cards"].pop()
    deck["drawn"] += 1
    return card


def remaining(deck: Dict) -> int:
    return len(deck["cards"])


def pick_injury_target(off_players: List[Dict]) -> Optional[Dict]:
    """40% QB, else random RB/WR/TE/K (healthy starters only).

    Falls back to any healthy starter if the preferred pool is empty.
    Returns the player dict actually chosen (already tagged as injured).
    """
    healthy_starters = [p for p in off_players if p.get("starter") and not p.get("injured")]
    if not healthy_starters:
        return None

    prefer_qb = random.random() < 0.40
    pool: List[Dict] = []
    if prefer_qb:
        pool = [p for p in healthy_starters if p.get("pos") == "QB"]
    if not pool:
        pool = [p for p in healthy_starters if p.get("pos") in ("RB", "WR", "TE", "K")]
    if not pool:
        pool = healthy_starters
    return random.choice(pool)


def apply_concussion(off_players: List[Dict], victim: Dict) -> Dict:
    """Mark victim as injured/out and promote first healthy backup at that pos.

    Returns an injury descriptor dict for the play event.
    """
    for p in off_players:
        if p["name"] == victim["name"]:
            p["starter"] = False
            p["injured"] = True
            p["injury_desc"] = "Concussion (out for game)"
    pos = victim.get("pos")
    healthy_at_pos = [p for p in off_players if p.get("pos") == pos and not p.get("injured")]
    if healthy_at_pos and not any(p.get("starter") for p in healthy_at_pos):
        healthy_at_pos.sort(key=lambda x: -x.get("ovr", 0))
        healthy_at_pos[0]["starter"] = True
        replacement = healthy_at_pos[0]["name"]
    else:
        replacement = None
    return {
        "player": victim["name"],
        "pos": pos,
        "team": victim.get("team"),
        "desc": "Concussion — OUT for game",
        "replacement": replacement,
    }
