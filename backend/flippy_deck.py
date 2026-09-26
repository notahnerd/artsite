"""Flippy Deck — a 250-card pre-play deck that either resolves the play
directly (100 result cards) or delegates to the dice engine (150 dice cards).

Composition (exactly 250):
- 150 DICE cards        → "Roll the dice" (default flow)
- 98  YARDS cards       → override play yards; range -6 .. +45
- 2   INJURY cards      → concussion, random offensive starter out for game;
                           40% chance it is the QB

The yardage curve is realistic-ish (right-skewed with rare breakaways).

Franchise custom decks (v6.1)
- `preset`: one of the SKEW presets below (Balanced / Power Run / Air Raid / Chaos)
- `signature_cards`: manager-authored yardage cards that REPLACE some DICE cards
  (deck stays at 250). Cap: 3 signature cards, 1-3 copies each.
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

# --- Yardage-skew presets (each sums to 98) ---
_POWER_RUN_CURVE = [
    (-4, 1), (-3, 2), (-2, 4), (-1, 6),
    (0, 8),  (1, 10), (2, 12), (3, 12), (4, 11), (5, 9),
    (6, 7),  (7, 5),  (8, 4),  (9, 3),  (10, 2),
    (12, 1), (15, 1),
]  # sum = 98
_AIR_RAID_CURVE = [
    (-8, 1), (-6, 2), (-5, 2), (-4, 3), (-3, 3), (-2, 3), (-1, 3),
    (0, 5),  (2, 4),  (4, 4),  (6, 5),  (8, 6),  (10, 7),
    (12, 7), (15, 6), (18, 5), (22, 5), (28, 4), (35, 4), (45, 3), (55, 2),
    (1, 3), (3, 4), (5, 3), (7, 3), (9, 3), (11, 2), (14, 1),
]  # let's recompute: 1+2+2+3+3+3+3=17 ; 5+4+4+5+6+7=31 ; 7+6+5+5+4+4+3+2=36 ; 3+4+3+3+3+2+1=19 ; total = 17+31+36+19 = 103
_CHAOS_CURVE = [
    (-10, 2), (-8, 2), (-6, 3), (-4, 3), (-2, 3),
    (0, 4),  (2, 4),  (4, 4),  (6, 5),  (8, 4),
    (10, 4), (15, 5), (20, 6), (25, 6), (30, 5), (35, 5),
    (40, 5), (45, 5), (50, 5), (55, 4), (60, 4), (70, 3), (80, 2),
]  # sum: 2+2+3+3+3=13; 4+4+4+5+4=21; 4+5+6+6+5+5=31; 5+5+5+4+4+3+2=28; total = 13+21+31+28 = 93 (adj needed)


def _normalize_curve(curve, target=98):
    """Trim or pad a curve so counts sum to `target`. Keeps proportional shape."""
    total = sum(c for _, c in curve)
    if total == target:
        return list(curve)
    # Simple padding/trim: adjust the last bucket
    out = list(curve)
    delta = target - total
    if delta > 0:
        # add copies to the smallest-yardage bucket (safer)
        out.append((out[len(out) // 2][0], delta))
    else:
        # trim from the largest bucket
        i = max(range(len(out)), key=lambda k: out[k][1])
        y, c = out[i]
        out[i] = (y, max(1, c + delta))
    return out


SKEW_PRESETS = {
    "balanced": _YARDAGE_CURVE,
    "power_run": _POWER_RUN_CURVE,
    "air_raid": _normalize_curve(_AIR_RAID_CURVE),
    "chaos":    _normalize_curve(_CHAOS_CURVE),
}

DECK_SIZE = 250
NUM_DICE = 150
NUM_INJURY = 2
MAX_SIGNATURE_CARDS = 3
MAX_SIGNATURE_COPIES = 3


def _sanitize_signature_cards(cards) -> List[Dict]:
    """Coerce user-supplied signature cards into a safe list."""
    if not cards:
        return []
    out = []
    for c in cards[:MAX_SIGNATURE_CARDS]:
        try:
            yards = int(c.get("yards", 0))
        except (TypeError, ValueError):
            continue
        yards = max(-10, min(60, yards))
        try:
            count = int(c.get("count", 1))
        except (TypeError, ValueError):
            count = 1
        count = max(1, min(MAX_SIGNATURE_COPIES, count))
        label = str(c.get("label") or f"Signature {yards:+d}")[:24]
        out.append({"label": label, "yards": yards, "count": count})
    return out


def _build_cards(config: Optional[Dict] = None) -> List[Dict]:
    config = config or {}
    preset_key = (config.get("preset") or "balanced").lower()
    curve = SKEW_PRESETS.get(preset_key, _YARDAGE_CURVE)
    signature_cards = _sanitize_signature_cards(config.get("signature_cards") or [])
    sig_total = sum(c["count"] for c in signature_cards)
    # Signature cards REPLACE DICE cards to keep deck exactly at 250
    dice_count = max(0, NUM_DICE - sig_total)

    cards: List[Dict] = []
    for _ in range(dice_count):
        cards.append({"type": "DICE"})
    for yards, count in curve:
        for _ in range(count):
            cards.append({"type": "YARDS", "yards": yards})
    for _ in range(NUM_INJURY):
        cards.append({"type": "INJURY"})
    for sig in signature_cards:
        for _ in range(sig["count"]):
            cards.append({
                "type": "YARDS",
                "yards": sig["yards"],
                "signature": True,
                "label": sig["label"],
            })
    # Ensure deck size; if short (curve mismatch), pad with DICE; if long, trim DICE
    while len(cards) < DECK_SIZE:
        cards.append({"type": "DICE"})
    while len(cards) > DECK_SIZE:
        # Remove a DICE card first (never remove YARDS/INJURY)
        for i in range(len(cards) - 1, -1, -1):
            if cards[i]["type"] == "DICE":
                cards.pop(i)
                break
        else:
            break
    return cards


def new_deck(config: Optional[Dict] = None) -> Dict:
    """Return a fresh shuffled deck state, optionally with a franchise config."""
    cards = _build_cards(config)
    random.shuffle(cards)
    return {
        "cards": cards,
        "drawn": 0,
        "reshuffles": 0,
        "config": config or {},
    }


def draw(deck: Dict) -> Dict:
    """Pop the next card. Auto-reshuffle when empty using the same config."""
    if not deck["cards"]:
        deck["cards"] = _build_cards(deck.get("config"))
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
