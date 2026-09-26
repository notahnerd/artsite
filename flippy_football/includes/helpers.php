<?php
/**
 * JSON I/O helpers, auth token check, rate limiter.
 * Kept in one file since they're all short primitives.
 */

function json_response(mixed $payload, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function json_body(): array {
    $cfg = require __DIR__ . '/../config.php';
    $raw = file_get_contents('php://input') ?: '';
    if (strlen($raw) > $cfg['security']['max_body_bytes']) {
        json_response(['detail' => 'Request body too large'], 413);
    }
    if ($raw === '') return [];
    $j = json_decode($raw, true);
    if (!is_array($j)) json_response(['detail' => 'Invalid JSON body'], 400);
    return $j;
}

function require_owner_token(string $seasonId, ?string $token): array {
    $stmt = db()->prepare('SELECT * FROM seasons WHERE id = ? LIMIT 1');
    $stmt->execute([$seasonId]);
    $row = $stmt->fetch();
    if (!$row) json_response(['detail' => 'Season not found'], 404);
    if (!empty($row['owner_token']) && $row['owner_token'] !== ($token ?? '')) {
        json_response(['detail' => 'Invalid owner token'], 403);
    }
    return $row;
}

function load_season(string $seasonId): array {
    $stmt = db()->prepare('SELECT * FROM seasons WHERE id = ? LIMIT 1');
    $stmt->execute([$seasonId]);
    $row = $stmt->fetch();
    if (!$row) json_response(['detail' => 'Season not found'], 404);
    return $row;
}

/**
 * Sliding-window rate limiter. Stores hits in the `rate_limit_hits` table.
 * $bucket examples: "sim_game:1.2.3.4", "create:1.2.3.4"
 */
function rate_limit(string $bucket, int $perMinute): void {
    $pdo = db();
    $pdo->prepare('DELETE FROM rate_limit_hits WHERE hit_at < (NOW() - INTERVAL 1 MINUTE)')->execute();
    $count = $pdo->prepare('SELECT COUNT(*) FROM rate_limit_hits WHERE ip_bucket = ? AND hit_at >= (NOW() - INTERVAL 1 MINUTE)');
    $count->execute([$bucket]);
    if ((int)$count->fetchColumn() >= $perMinute) {
        json_response(['detail' => 'Rate limit exceeded'], 429);
    }
    $pdo->prepare('INSERT INTO rate_limit_hits (ip_bucket) VALUES (?)')->execute([$bucket]);
}

function client_ip(): string {
    return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function owner_token_header(): ?string {
    return $_SERVER['HTTP_X_OWNER_TOKEN'] ?? null;
}

function require_body_field(array $body, string $field): mixed {
    if (!array_key_exists($field, $body)) {
        json_response(['detail' => "Missing field: $field"], 400);
    }
    return $body[$field];
}

/** Coerce all *_json columns from string to decoded array. */
function decode_season(array $row): array {
    foreach ([
        'schedule_json'          => 'schedule',
        'depth_charts_json'      => 'depth_charts',
        'injuries_json'          => 'injuries',
        'trades_json'            => 'trades',
        'coaching_json'          => 'coaching',
        'playbook_json'          => 'playbook',
        'extra_rivals_json'      => 'extra_rivals',
        'deck_config_json'       => 'deck_config',
        'playoffs_json'          => 'playoffs',
        'champions_history_json' => 'champions_history',
        'franchise_rosters_json' => 'franchise_rosters',
    ] as $col => $key) {
        $row[$key] = $row[$col] ? json_decode($row[$col], true) : null;
        unset($row[$col]);
    }
    return $row;
}

function uuidv4(): string {
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}
