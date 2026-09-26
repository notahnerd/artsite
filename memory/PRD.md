# Gridiron Roller - Tabletop NFL Simulator

## Original Problem
NFL football simulation game with dice rolls and chart-based outcomes using real player ratings.

## All Implemented Features

### Core Sim (v1)
- 32 NFL teams w/ 2025 ratings, 7 star players each + auto-generated backups
- 18-week randomized regular season
- Strat-O-Matic dice: 3 white dice (chart 3–18) + 1 red die (1–3 read OFFENSE card, 4–6 read team DEFENSE card)
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

### Sim Refinements (v5 - 2026-02)
- **Kicker Card**: every kicker now has their own 3–18 accuracy chart (WIDE LEFT/RIGHT → PURE → BOOMSTICK) with a personal max range; weather kicking penalties trim range in bad conditions
- **Custom Playbook**: per-team run/pass tendency slider stacks on top of coaching philosophy (Run Heavy → Pass Heavy, five steps)
- **Rivalry System**: division opponents + legacy pairs (KC-LV, BAL-PIT, DAL-PHI, GB-CHI, etc.) are auto-flagged; managers can pick one extra rival; rivalry games get a +1 chart bump for extra big plays

## New Endpoints
- POST /api/season/playbook, POST /api/season/rival, GET /api/season/{id}/rivals/{team}

## New Components
- PlaybookPicker, RivalPickerDialog, RivalryBadge


### Security Hardening (v6 - 2026-02-26)
- **Per-season ownership token**: create_season now returns an `owner_token` (32-byte urlsafe). All mutating endpoints require the token via `X-Owner-Token` header. Reads are public; token is stripped from GET /season/{id} responses. Legacy seasons without a token are grandfathered open.
- **Rate limits (slowapi)**: create=10/min, sim-game=60/min, sim-week=30/min, playoff-game=60/min, next-year=5/min, default 120/min per-IP.
- **Security headers**: X-Content-Type-Options: nosniff, X-Frame-Options: DENY, Referrer-Policy: strict-origin-when-cross-origin, Strict-Transport-Security (1yr), Permissions-Policy; 1 MB request body cap.
- **CORS hardening**: allow_credentials auto-disabled when origins is wildcard; scoped methods/headers.
- **Team-id validation**: coaching, playbook, rival, depth-chart, trade endpoints reject unknown team IDs (400).
- **Dependency trim**: removed unused sensitive deps (python-jose, pyjwt, passlib, bcrypt, boto3, emergentintegrations); added slowapi.

### Flippy Deck (v6 - 2026-02-26, expanded v6.2)
- **350-card pre-play deck per game**: 250 "ROLL DICE" cards + 98 yardage cards (-6 .. +45, realistic right-skewed curve, 2× 45-yd breakaways) + 2 concussion injury cards. Auto-reshuffles when empty.
- **Draw trigger**: RUN/PASS scrimmage plays only. PUNT/FG/XP/Kickoff never draw a card.
- **YARDS card override**: card.yards replaces dice-chart yards; TDs, first downs and turnover-on-downs still computed from ball position. Random fumble roll suppressed on card overrides.
- **INJURY card**: 40% QB / 60% RB/WR/TE/K; victim marked injured & out for game, first healthy backup auto-promoted.
- **Frontend**: `FlippyDeck.jsx` — 3D CSS flip animation, purple-stripe back with "FLIPPY DECK" branding, remaining pill (X/250), color-coded faces (breakaway green, chunk emerald, loss rose, injury rose). Toast on breakaway and concussion.

## Files Added
- /app/backend/flippy_deck.py
- /app/frontend/src/components/FlippyDeck.jsx
- /app/backend/tests/backend_test.py, /app/backend/tests/test_flippy_deck.py (regression)

## Bug Fixes
- 2026-02-26 — Home.jsx create-season flow bypassed the createSeason() wrapper, so the returned owner_token was never persisted; every new UI-created season failed sim-game with 403 ("loading error"). Fixed by importing saveOwnerToken and calling it after api.post('/season/create').


### Custom Flippy Deck (v6.1 - 2026-02-26)
- **Franchise-scoped deck builder unlocked after 2 completed seasons** (`champions_history` length >= 2).
- **4 yardage-skew presets**: Balanced, Power Run (grind), Air Raid (boom/bust), Chaos (-10..+80).
- **Up to 3 signature cards**, each label (24 char) + yards (clamp -10..+60) + count (1-3). Signature cards replace DICE cards so deck stays at 250.
- **Config persists on season doc** as `deck_config` and is inherited by future seasons via `/api/season/next-year`.
- **API**: `GET /api/season/{id}/deck-config` (public read), `POST /api/season/deck-config` (owner-token required, 403 when locked, 400 on invalid preset).
- **Frontend**: `DeckBuilder.jsx` dialog opened from Season page's "Flippy Deck" button. Locked state shows seasons-played count; unlocked shows preset picker + signature editor + save.
- **FlippyDeck.jsx** now renders a distinct "SIGNATURE CARD" face (fuchsia) with the manager's custom label when a signature card is flipped; play toast fires on signature draws.

## Files Added
- /app/backend/flippy_deck.py (updated with SKEW_PRESETS + _sanitize_signature_cards + config-aware _build_cards)
- /app/frontend/src/components/DeckBuilder.jsx
- /app/backend/tests/test_deck_config.py (8 pytest cases, all passing)
