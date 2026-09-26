# Flippy Football — Gridiron Roller (PHP + MySQL port)

A **complete port** of the NFL tabletop simulator to PHP 8.1+ / MySQL — ready for cheap shared hosting. Server-rendered HTML pages, vanilla JS for the dice / flip-card animations, no build step, no npm.

## What's inside

```
flippy_football/
├── config.php               — env-driven DB + app config (edit or set env vars)
├── schema.sql               — MySQL schema (seasons, game_logs, rate_limit_hits)
├── includes/                — All the game logic
│   ├── db.php               — PDO singleton
│   ├── helpers.php          — JSON I/O, owner-token auth, rate limiter, security headers
│   ├── nfl_data.php         — 32 teams + rosters (Madden-style OVR)
│   ├── player_cards.php     — 3-18 Strat-O-Matic charts (QB/RB/WR/DEF/K)
│   ├── flippy_deck.php      — 350-card deck (250 DICE + 98 YARDS + 2 INJURY), 4 skew presets, signature cards
│   ├── weather.php          — 5 weather types, dome bias
│   ├── injuries.php         — random per-starter injury rolls
│   ├── rivalries.php        — division + legacy rivals, extra rival slot
│   ├── season.php           — schedule generator + standings
│   ├── playoffs.php         — 14-team bracket (WC → DIV → CONF → SB)
│   ├── franchise.php        — multi-year aging, retirements, rookies
│   └── sim_engine.php       — the beast: 3d6+1d6 chart resolution + play-by-play
├── public/                  — Web root (point Apache/Nginx here)
│   ├── index.php            — front controller + rate-limit gate
│   ├── api.php              — /api/* JSON dispatcher
│   ├── .htaccess            — clean URL rewrites for Apache
│   ├── assets/
│   │   ├── style.css        — full broadcast theme
│   │   └── game.js          — dice roll + flip animations + play pacing
│   └── pages/
│       ├── _layout.php      — HTML shell (nav, footer)
│       ├── home.php         — team picker + season creator
│       ├── season.php       — dashboard: schedule, standings, matchup panel
│       ├── game.php         — live scoreboard + dice + Flippy Deck + play feed
│       └── playoffs.php     — 14-team bracket
└── cli/
    └── sim.php              — one-off game sim from the terminal
```

## Requirements

- **PHP 8.1+** (`typed properties`, `match`, `str_starts_with`) with `pdo_mysql` extension
- **MySQL 5.7+ / MariaDB 10.3+**
- Apache with `mod_rewrite`, **or** Nginx with a rewrite rule to `index.php`

That's it. No Composer, no Node, no Docker required.

## Install on shared hosting (cPanel / DirectAdmin / Plesk)

1. **Upload** the whole `flippy_football/` folder somewhere outside your web root (e.g., `/home/you/flippy_football`).
2. **Point your web-root / addon domain** to `flippy_football/public/`.
3. **Create the database** in your host's control panel and import `schema.sql`.
4. **Configure DB creds** — either edit `config.php` directly, or set environment variables (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`) in cPanel's Env Vars section.
5. Visit `https://your-domain.com/` — you should see the team picker.

### Local dev (Mac/Linux)

```bash
# Set up DB
mysql -u root -p -e "CREATE DATABASE flippy_football;"
mysql -u root -p flippy_football < schema.sql

# Run PHP's built-in server
export DB_HOST=localhost DB_NAME=flippy_football DB_USER=root DB_PASS=''
cd flippy_football
php -S localhost:9080 -t public

# Open http://localhost:9080
```

### Quick CLI sim (no web server needed)

```bash
php cli/sim.php KC BUF --difficulty=arcade --rivalry
php cli/sim.php PHI SF --deck-preset=chaos
```

## Game features

| Category | Included |
| --- | --- |
| **Roster / ratings** | 32 teams, real 2025 starters, Madden-style OVR 60-99, auto-generated backups |
| **Sim engine** | 3d6 chart index + 1d6 offense/defense read (Strat-O-Matic style) |
| **Flippy Deck** | 350 cards per game: 250 DICE + 98 YARDS (-6..+45) + 2 INJURY, auto-reshuffle |
| **Skew presets** | Balanced / Power Run / Air Raid / Chaos (chaos → -10..+80 range) |
| **Signature cards** | Up to 3 manager-authored cards, unlocked after 2 completed seasons |
| **Weather** | Clear, Rain, Snow, Wind, Dome — modifies pass yards / kicks |
| **Coaching** | Aggressive / Balanced / Conservative — changes 4th-down + pass tendency |
| **Playbook** | Per-team `pass_bias` slider (-0.25 to +0.25) |
| **Rivalries** | Division auto-rivals + legacy pairs (KC-LV, DAL-PHI, GB-CHI, etc.) — +8% intensity |
| **Injuries** | Per-starter concussion rolls (post-game) + concussion card (in-game) |
| **Playoffs** | 14-team bracket, WC → DIV → CONF → SB, top seed bye |
| **Franchise** | Multi-year progression: age/retire/rookie draft class |
| **Persistence** | MySQL — every season, game log, standings survives restart |
| **Security** | Owner-token check on all mutations, sliding-window rate limits, HSTS + X-Frame-Options |

## API endpoints

All endpoints under `/api/`. Mutations require `X-Owner-Token` header (returned from `/api/season/create`).

| Verb | Path | Purpose |
| --- | --- | --- |
| GET  | `/api/teams` | List all 32 teams |
| GET  | `/api/teams/{id}` | Single team + starters + backups |
| POST | `/api/season/create` | Create a new franchise season (returns `owner_token`) |
| GET  | `/api/season/{id}` | Season doc + computed standings |
| POST | `/api/season/sim-game` | Sim a single game — returns full play-by-play + saves `log_id` |
| POST | `/api/season/sim-week` | Sim all remaining games in the current week |
| GET  | `/api/game-log/{log_id}` | Full play-by-play + player stats |
| POST | `/api/season/coaching` | Set a team's coaching philosophy |
| POST | `/api/season/playbook` | Set a team's pass bias |
| GET  | `/api/season/{id}/deck-config` | Read Flippy Deck config + unlock status |
| POST | `/api/season/deck-config` | Save custom Flippy Deck (locked until 2 seasons) |
| POST | `/api/season/{id}/start-playoffs` | Seed the 14-team bracket |
| GET  | `/api/season/{id}/rivals/{team}` | Division + legacy + user-picked rivals for a team |

## Security defaults

- Every response ships with `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: strict-origin-when-cross-origin`, `Strict-Transport-Security: max-age=31536000`.
- Owner-token is a `bin2hex(random_bytes(32))` = 64 hex chars, stored per-season; client persists in `localStorage.gr_owner_tokens`.
- Sliding-window rate limiter uses a small `rate_limit_hits` table — 120 req/min per IP globally, 60/min for sim-game, 10/min for season-create.
- Request bodies over 1 MB are rejected at the JSON parser.

## Things the Python original has that this port doesn't (yet)

- Depth-chart editor UI (backend logic ready)
- Trade dialog UI
- Injury news ticker
- Player card deep-dive page
- Stat leaders page
- Franchise dashboard UI (backend ready via next-year endpoint — not wired yet)

Add them as you go — the sim engine and API are complete. Everything you need is either an endpoint or a plain PHP page you extend.

## Deployment notes

- **Apache:** the included `.htaccess` handles rewrites. Just point the docroot at `public/`.
- **Nginx:** add this location block to your server file:
  ```nginx
  location / {
      try_files $uri $uri/ /index.php?$query_string;
  }
  location ~ \.php$ {
      fastcgi_pass unix:/var/run/php-fpm/www.sock;
      fastcgi_index index.php;
      include fastcgi_params;
  }
  ```
- **Shared hosting without shell access:** upload via FTP, set the docroot to `public/` in cPanel, import `schema.sql` via phpMyAdmin. Should just work.

## License / credits

Made with the Gridiron Roller — a tabletop simulation of pro football. Player names & team likenesses used editorially for a simulation prototype.
