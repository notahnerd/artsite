# Gridiron Roller - Tabletop NFL Simulator

## Original Problem
NFL football simulation game with chart results and dice roll. Real player ratings and stats.

## Implemented Features

### Core (v1)
- 32 NFL teams w/ 2025 ratings, 7 star players each + auto-generated backups
- 18-week randomized season schedule
- Strat-O-Matic style Player Cards (QB/RB/WR/TE/DEF) — every player has a 2D6 chart
- Animated Game screen: scoreboard, field w/ ball & line markers, dice, play-by-play, box score
- Real-time toasts for TD/INT/FUMBLE/FG
- Player Card Vault w/ per-team browsing and stats bars

### Postseason & Depth (v2 - 2026-02)
- **Playoff Bracket**: 14-team bracket (7 seeds/conf), Wild Card / Divisional / Conf / Super Bowl w/ auto-advancement + OT
- **Stat Leaders**: cumulative passing/rushing/receiving/sacks across whole season
- **Depth Chart**: choose QB/RB/K starter — backup cards shift game outcomes
- **Weather**: per-game weather roll (Clear/Rain/Snow/Wind/Dome) that modifies pass card yardage, INT rate, big play chance, and FG kicking

## Architecture
- Backend: FastAPI + Motor MongoDB
  - Modules: nfl_data, player_cards, sim_engine, season, playoffs, weather
  - Endpoints: /api/teams, /api/team-cards, /api/player-card, /api/season/*, /api/season/depth-chart, /api/season/{id}/leaders, /api/season/{id}/start-playoffs, /api/season/playoff-game
- Frontend: React + shadcn/ui + Sonner
  - Pages: Home, Season, Game (regular+playoff), Chart, LeadersPage, Playoffs
  - Components: Scoreboard, Field, Dice, PlayByPlay, BoxScore, PlayerCard, DepthChartDialog, WeatherBadge, Leaders

## Backlog / Next
- P1: Franchise mode across multiple seasons (draft, contracts)
- P1: Player injuries and streaks
- P2: Custom league creation with friends
- P2: Coaching philosophy toggles (aggressive/conservative)
