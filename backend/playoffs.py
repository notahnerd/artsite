"""Playoff bracket generation for NFL simulator.
14-team playoffs: top 7 per conference. #1 seed gets Wild Card bye.
Wild Card: (2 vs 7), (3 vs 6), (4 vs 5) — home team = higher seed
Divisional: 1 seed vs lowest surviving; other two winners face
Conf Championship: 1 game per conference
Super Bowl: neutral site
"""
from typing import Dict, List


ROUND_LABELS = {
    "WC": "Wild Card",
    "DIV": "Divisional",
    "CONF": "Conference Championship",
    "SB": "Super Bowl",
}


def seed_playoffs(standings: List[Dict]) -> Dict[str, List[Dict]]:
    """Return top-7 seeds per conference."""
    afc = [s for s in standings if s["conf"] == "AFC"][:7]
    nfc = [s for s in standings if s["conf"] == "NFC"][:7]
    for i, t in enumerate(afc):
        t["seed"] = i + 1
    for i, t in enumerate(nfc):
        t["seed"] = i + 1
    return {"AFC": afc, "NFC": nfc}


def build_bracket(seeds: Dict[str, List[Dict]]) -> Dict:
    """Build initial bracket structure with placeholders for later rounds."""
    games = []
    game_id = 1
    for conf in ("AFC", "NFC"):
        s = seeds[conf]
        # Wild Card: (2v7 as WC1), (3v6 as WC2), (4v5 as WC3). #1 bye.
        pairs = [(1, 6), (2, 5), (3, 4)]  # indices into 7-team seed list (0-indexed)
        for idx, (hi, lo) in enumerate(pairs):
            games.append({
                "id": f"{conf}-WC-{idx+1}",
                "round": "WC",
                "conf": conf,
                "home": s[hi]["team"],
                "away": s[lo]["team"],
                "home_seed": s[hi]["seed"],
                "away_seed": s[lo]["seed"],
                "played": False,
                "home_score": None,
                "away_score": None,
                "winner": None,
                "log_id": None,
            })
            game_id += 1
    # Divisional placeholder (4 games)
    for conf in ("AFC", "NFC"):
        for i in range(2):
            games.append({
                "id": f"{conf}-DIV-{i+1}",
                "round": "DIV",
                "conf": conf,
                "home": None,  # will fill after WC completes
                "away": None,
                "home_seed": None,
                "away_seed": None,
                "played": False,
                "home_score": None,
                "away_score": None,
                "winner": None,
                "log_id": None,
            })
    # Conf Championship placeholder (2 games)
    for conf in ("AFC", "NFC"):
        games.append({
            "id": f"{conf}-CONF-1",
            "round": "CONF",
            "conf": conf,
            "home": None,
            "away": None,
            "home_seed": None,
            "away_seed": None,
            "played": False,
            "home_score": None,
            "away_score": None,
            "winner": None,
            "log_id": None,
        })
    # Super Bowl
    games.append({
        "id": "SB",
        "round": "SB",
        "conf": "NEUTRAL",
        "home": None,
        "away": None,
        "home_seed": None,
        "away_seed": None,
        "played": False,
        "home_score": None,
        "away_score": None,
        "winner": None,
        "log_id": None,
    })
    return {"seeds": seeds, "games": games, "champion": None}


def _seed_of(bracket, team_id):
    for conf in ("AFC", "NFC"):
        for t in bracket["seeds"][conf]:
            if t["team"] == team_id:
                return t["seed"]
    return 99


def _conf_of(bracket, team_id):
    for conf in ("AFC", "NFC"):
        for t in bracket["seeds"][conf]:
            if t["team"] == team_id:
                return conf
    return None


def advance_bracket(bracket: Dict, played_game_id: str, winner_id: str):
    """After a game is played, fill in the next round's slots."""
    games = bracket["games"]
    played = next((g for g in games if g["id"] == played_game_id), None)
    if not played:
        return bracket
    played["winner"] = winner_id
    # Fill next round
    if played["round"] == "WC":
        conf = played["conf"]
        # Wait for all 3 WC games in this conf to complete
        wc_games = [g for g in games if g["round"] == "WC" and g["conf"] == conf]
        if all(g["played"] for g in wc_games):
            winners = [(g["winner"], _seed_of(bracket, g["winner"])) for g in wc_games]
            winners.sort(key=lambda x: x[1])  # best seed first
            # 1 seed plays lowest surviving; middle two play each other
            top_seed = next(t for t in bracket["seeds"][conf] if t["seed"] == 1)
            div1 = next(g for g in games if g["id"] == f"{conf}-DIV-1")
            div2 = next(g for g in games if g["id"] == f"{conf}-DIV-2")
            div1["home"] = top_seed["team"]
            div1["home_seed"] = 1
            div1["away"] = winners[-1][0]  # lowest seed remaining
            div1["away_seed"] = winners[-1][1]
            div2["home"] = winners[0][0]
            div2["home_seed"] = winners[0][1]
            div2["away"] = winners[1][0]
            div2["away_seed"] = winners[1][1]
    elif played["round"] == "DIV":
        conf = played["conf"]
        div_games = [g for g in games if g["round"] == "DIV" and g["conf"] == conf]
        if all(g["played"] for g in div_games):
            winners = [(g["winner"], _seed_of(bracket, g["winner"])) for g in div_games]
            winners.sort(key=lambda x: x[1])
            cc = next(g for g in games if g["id"] == f"{conf}-CONF-1")
            cc["home"] = winners[0][0]  # better seed hosts
            cc["home_seed"] = winners[0][1]
            cc["away"] = winners[1][0]
            cc["away_seed"] = winners[1][1]
    elif played["round"] == "CONF":
        conf_games = [g for g in games if g["round"] == "CONF"]
        if all(g["played"] for g in conf_games):
            afc_win = next(g for g in conf_games if g["conf"] == "AFC")["winner"]
            nfc_win = next(g for g in conf_games if g["conf"] == "NFC")["winner"]
            sb = next(g for g in games if g["id"] == "SB")
            # Convention: AFC as away, NFC as home (alternates in reality; keep simple)
            sb["home"] = afc_win
            sb["away"] = nfc_win
            sb["home_seed"] = _seed_of(bracket, afc_win)
            sb["away_seed"] = _seed_of(bracket, nfc_win)
    elif played["round"] == "SB":
        bracket["champion"] = winner_id
    return bracket
