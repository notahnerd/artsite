"""Game-day weather rolls and their effect on player cards.
Weather is rolled per game (skewed by home dome) and modifies pass outcomes.
"""
import random

WEATHER_TYPES = {
    "CLEAR": {
        "label": "Clear",
        "pass_mult": 1.0,
        "int_bonus": 0,
        "big_play_mult": 1.0,
        "fg_penalty": 0,
        "kick_penalty": 0,
        "icon": "sun",
        "color": "#F59E0B",
    },
    "RAIN": {
        "label": "Rain",
        "pass_mult": 0.82,
        "int_bonus": 1,
        "big_play_mult": 0.85,
        "fg_penalty": 4,
        "kick_penalty": 3,
        "icon": "cloud-rain",
        "color": "#38BDF8",
    },
    "SNOW": {
        "label": "Snow",
        "pass_mult": 0.7,
        "int_bonus": 1,
        "big_play_mult": 0.65,
        "fg_penalty": 8,
        "kick_penalty": 6,
        "icon": "cloud-snow",
        "color": "#E2E8F0",
    },
    "WIND": {
        "label": "Windy",
        "pass_mult": 0.9,
        "int_bonus": 0,
        "big_play_mult": 0.5,
        "fg_penalty": 10,
        "kick_penalty": 6,
        "icon": "wind",
        "color": "#A5F3FC",
    },
    "DOME": {
        "label": "Dome",
        "pass_mult": 1.05,
        "int_bonus": -1,
        "big_play_mult": 1.1,
        "fg_penalty": -3,
        "kick_penalty": -2,
        "icon": "home",
        "color": "#C084FC",
    },
}

DOMED_TEAMS = {"MIN", "DET", "NO", "ATL", "LV", "IND", "LAR", "ARI", "HOU", "DAL"}


def roll_weather(home_id: str) -> str:
    if home_id in DOMED_TEAMS and random.random() < 0.9:
        return "DOME"
    r = random.random()
    if r < 0.62:
        return "CLEAR"
    if r < 0.78:
        return "WIND"
    if r < 0.92:
        return "RAIN"
    return "SNOW"


def weather_info(w: str) -> dict:
    return {"code": w, **WEATHER_TYPES[w]}
