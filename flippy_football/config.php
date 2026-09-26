<?php
/**
 * App config. Reads from environment first, falls back to sensible defaults for
 * shared-hosting cPanel-style deployments.
 *
 * On shared hosting: create a .env-style include OR set these via cPanel's
 * "Environment Variables" section. Never commit real DB credentials.
 */
return [
    'db' => [
        'host' => getenv('DB_HOST') ?: 'localhost',
        'name' => getenv('DB_NAME') ?: 'flippy_football',
        'user' => getenv('DB_USER') ?: 'root',
        'pass' => getenv('DB_PASS') ?: '',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'name'      => 'Gridiron Roller',
        'base_url'  => getenv('APP_BASE_URL') ?: '',
        'season_year' => 2025,
        'debug'     => (bool)(getenv('APP_DEBUG') ?: false),
    ],
    'security' => [
        // Simple rolling per-IP request cap. Tune per host.
        'rate_limit_per_min' => 120,
        'sim_game_per_min'   => 60,
        'create_season_per_min' => 10,
        // Max request body bytes for POST endpoints (protects sim endpoints)
        'max_body_bytes' => 1024 * 1024,
    ],
];
