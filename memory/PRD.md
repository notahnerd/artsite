# Gridiron Roller - Tabletop NFL Simulator

## Original Problem
NFL football simulation game with dice rolls and chart-based outcomes using real player ratings.

## Implemented Features

### Core (v1)
- 32 NFL teams w/ 2025 ratings, 7 star players each + auto-generated backups
- 18-week randomized regular season
- Strat-O-Matic style Player Cards for QB/RB/WR/TE/K/DEF
- Animated Game screen: scoreboard, field w/ ball & line markers, dice, play-by-play, box score
- Sonner toasts for TD/INT/FUMBLE/FG
- Player Card Vault w/ per-team browsing

### Postseason + Depth (v2)
- Playoff bracket: 14-team, Wild Card → Divisional → Conf → Super Bowl w/ auto-seeding + OT
- Stat Leaders: cumulative passing/rushing/receiving/sacks
- Depth Chart: choose starters at QB/RB/K; backup cards shift outcomes
- Weather system: Clear/Rain/Snow/Wind/Dome modifies pass yardage, INTs, big plays, FG kicking

### Realism Layer (v3 - 2026-02)
- **Injury Rolls**: ~4.5% chance per starter per game; puts them out 1-8 weeks; live injury report on Season page; auto-promotes backup card
- **Playoff Race Picture**: In/Bubble/Out badges per conference starting Week 6
- **Difficulty Tuner**: Arcade / Balanced / Realistic (yardage multipliers 1.0 / 0.7 / 0.5) - set at season creation and changeable mid-season
- **Trade Deadline (Week 8)**: swap a starter for another team's same-position starter; persisted on season; sim engine picks up traded rosters immediately

## Architecture
- Backend: FastAPI + Motor MongoDB
  - Modules: nfl_data, player_cards, sim_engine, season, playoffs, weather, injuries
  - Endpoints: /api/teams, /api/team-cards, /api/player-card, /api/season/*, /api/season/depth-chart, /api/season/{id}/leaders, /api/season/{id}/playoff-race, /api/season/{id}/injuries, /api/season/trade, /api/season/difficulty, /api/season/{id}/start-playoffs, /api/season/playoff-game
- Frontend: React + shadcn/ui + Sonner
  - Pages: Home, Season, Game (regular+playoff), Chart, LeadersPage, Playoffs
  - Components: Scoreboard, Field, Dice, PlayByPlay, BoxScore, PlayerCard, DepthChartDialog, WeatherBadge, Leaders, PlayoffRace, InjuryReport, TradeDialog, DifficultyPicker

## Backlog
- P1: Multi-year franchise (draft + free agency)
- P1: Coaching philosophy toggles (aggressive/conservative playcalling)
- P2: Custom league creation w/ friends
- P2: Historical top-100 all-time roster mode
