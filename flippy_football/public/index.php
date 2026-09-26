<?php
/**
 * Gridiron Roller — front controller.
 *
 * Route pattern: everything is routed through here by .htaccess or a hosting-side rewrite.
 * URL scheme:
 *   /                          → home page (team picker + season creator)
 *   /season/{id}               → season dashboard
 *   /season/{id}/game/{gid}    → live-play game view
 *   /season/{id}/playoffs      → playoff bracket
 *   /api/*                     → JSON API (routed via api.php)
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

// ---------- Rate limit (global cap per IP) ----------
$cfg = require __DIR__ . '/../config.php';
try {
    rate_limit('global:' . client_ip(), $cfg['security']['rate_limit_per_min']);
} catch (Throwable $e) {
    // If DB is unavailable, don't hard-fail — you're probably setting up.
    if (str_contains(strtolower($_SERVER['REQUEST_URI'] ?? ''), '/api/')) {
        header('Content-Type: application/json');
        echo json_encode(['detail' => 'Database not initialized. Run schema.sql.']);
        exit;
    }
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = rtrim($path, '/') ?: '/';

// ---------- API routing ----------
if (str_starts_with($path, '/api/')) {
    require __DIR__ . '/api.php';
    exit;
}

// ---------- Static assets are served directly by the web server ----------

// ---------- Page routing ----------
$pageParams = [];
if ($path === '/') {
    $page = 'home';
} elseif (preg_match('#^/season/([0-9a-f-]{36})/game/([\w-]+)$#i', $path, $m)) {
    $page = 'game'; $pageParams = ['season_id' => $m[1], 'game_id' => $m[2]];
} elseif (preg_match('#^/season/([0-9a-f-]{36})/playoffs$#i', $path, $m)) {
    $page = 'playoffs'; $pageParams = ['season_id' => $m[1]];
} elseif (preg_match('#^/season/([0-9a-f-]{36})$#i', $path, $m)) {
    $page = 'season'; $pageParams = ['season_id' => $m[1]];
} else {
    http_response_code(404);
    $page = 'notfound';
}

$appName = $cfg['app']['name'];
include __DIR__ . '/pages/_layout.php';
