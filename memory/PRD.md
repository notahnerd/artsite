# Gridiron Roller - Tabletop NFL Simulator

## Original Problem
NFL football simulation game with dice rolls and chart-based outcomes using real player ratings.

## All Implemented Features

### Core Sim (v1)
- 32 NFL teams w/ 2025 ratings, 7 star players each + auto-generated backups
- 18-week randomized regular season
- Strat-O-Matic style Player Cards (QB/RB/WR/TE/K/DEF)
- Animated Game screen: scoreboard, field w/ ball & line markers, dice, play-by-play, box score

### Postseason & Depth (v2)
- 14-team Playoff bracket (Wild Card → Divisional → Conf → Super Bowl) w/ OT
- Stat Leaders (passing/rushing/receiving/sacks)
- Depth Chart (QB/RB/K starter picker w/ backup card takeover)
- Weather: Clear/Rain/Snow/Wind/Dome modifies pass yardage, INTs, big plays, FG

### Realism Layer (v3)
- Injury Rolls (~4.5%/starter/game, 1–8 wk out) w/ auto backup promotion
- Playoff Race picture (In/Bubble/Out) from Week 6
- Difficulty Tuner (Arcade / Balanced / Realistic yardage multipliers)
- Trade Deadline at Week 8 (same-position swap)

### Coach & Franchise (v4 - 2026-02)
- **Coaching Philosophy** (Conservative / Balanced / Aggressive): changes 4th-down decisions, 2-point tries, pass/run mix
- **Live Injury News Feed**: rolling ticker at top of Game screen with league injuries
- **Head-to-Head Sharing**: public `/share/game/:logId` recap page w/ scores, team stats, top plays; "Copy Recap Link" from Game screen
- **Franchise Mode**: multi-year rollover — player aging, ~15–40% retirement chance for 33+, rookie draft class per team, trophy case, continuing coaching/difficulty across years

## Architecture
- Backend modules: nfl_data, player_cards, sim_engine, season, playoffs, weather, injuries, franchise
- Endpoints: /api/teams, /api/team-cards, /api/player-card, /api/season/*, /api/season/depth-chart, /api/season/{id}/leaders, /api/season/{id}/playoff-race, /api/season/{id}/injuries, /api/season/trade, /api/season/difficulty, /api/season/coaching, /api/season/{id}/franchise, /api/season/next-year, /api/season/{id}/start-playoffs, /api/season/playoff-game, /api/game-log/{id}/share
- Frontend pages: Home, Season, Game (regular+playoff), Chart, LeadersPage, Playoffs, Franchise, Share
- Frontend components: Scoreboard, Field, Dice, PlayByPlay, BoxScore, PlayerCard, DepthChartDialog, WeatherBadge, Leaders, PlayoffRace, InjuryReport, InjuryTicker, TradeDialog, DifficultyPicker, CoachingPicker
