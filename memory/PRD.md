# Gridiron Roller - Tabletop NFL Simulator

## Original Problem
A NFL football simulation game with chart results and dice roll. Real player ratings and stats.
Feature request (2026-02): Strat-O-Matic style — every player has their own card/chart based on their abilities.

## User Choices
- Season Mode: Manage a team across a full 18-week season
- Simulation: Dice roll (2D6) + Strat-O-Matic style PLAYER CARDS
- Data: Curated 2025 NFL seed data (32 teams, 7 star players each)
- Features: Play-by-play, dice rolls, live scoreboard, box score, stats, player card viewer
- Style: Bold stadium/broadcast dark theme with team colors, tabletop dice

## Architecture
- Backend: FastAPI + Motor (async MongoDB)
  - /api/teams, /api/teams/{id}
  - /api/team-cards/{team_id} - all player cards for a team
  - /api/player-card?team&name - single player card
  - /api/season/create, /api/season/{id}
  - /api/season/sim-game, /api/season/sim-week
  - /api/chart - reference charts
- Player Cards (player_cards.py):
  - QB card: 2-12 outcomes (INT/SACK/INCOMPLETE/NORMAL/BIG_PLAY/DEEP_BOMB), styles: gunslinger/balanced/game-manager
  - RB card: styles power/all-purpose/speed
  - WR/TE card: YAC bonus per roll + drop chance
  - Ratings skew yardage; team DEF adjusts final
- Frontend: React + shadcn/ui + Sonner toasts
  - Home: team selection grid by AFC/NFC divisions
  - Season: schedule, standings, roster (click players → card modal)
  - Game: scoreboard, animated field, dice, play-by-play, box score
  - Player Cards Vault: team picker + 3-col card grid with mini bar charts

## Implemented (2026-02)
- 32 team seed w/ realistic 2025 ratings and 7 star players each
- Season creation with 18-week randomized schedule
- Deterministic simulation engine using per-player cards
- Interactive Game screen with dice animation and Auto-sim
- Play-by-play cards, animated dice/ball, sonner toasts for TD/INT/FUMBLE/FG
- Standings, real-time box score, roster
- Player Card modal (accessible from Season roster or Player Cards Vault)
- Player Cards Vault page with all 32 teams

## Backlog / Next
- P1: Playoffs & Super Bowl bracket after Week 18
- P1: Season-long player stat leaders (passing/rushing/receiving)
- P2: Coaching decisions (4th down, timeouts, 2-point tries)
- P2: Custom lineup/depth chart management
- P2: Free agency + trade deadline
- P2: Multi-season franchise mode (draft, contracts)
