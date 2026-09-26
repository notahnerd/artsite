<?php
/**
 * JSON API dispatcher. Included by index.php when the path starts with /api/.
 * Every route here returns JSON.
 */
require_once __DIR__ . '/../includes/nfl_data.php';
require_once __DIR__ . '/../includes/sim_engine.php';
require_once __DIR__ . '/../includes/season.php';
require_once __DIR__ . '/../includes/playoffs.php';
require_once __DIR__ . '/../includes/franchise.php';
require_once __DIR__ . '/../includes/rivalries.php';

$cfg  = require __DIR__ . '/../config.php';
$path = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '', '/');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$body   = ($method === 'POST') ? json_body() : [];

// ---------- Public reads ----------
if ($path === '/api/teams' && $method === 'GET') {
    json_response(['teams' => NflData::TEAMS]);
}
if (preg_match('#^/api/teams/([A-Z]{2,3})$#', $path, $m) && $method === 'GET') {
    $t = NflData::getTeam($m[1]);
    if (!$t) json_response(['detail' => 'Team not found'], 404);
    json_response($t + ['players' => NflData::getPlayers($m[1])]);
}

// ---------- Season create ----------
if ($path === '/api/season/create' && $method === 'POST') {
    rate_limit('create:' . client_ip(), $cfg['security']['create_season_per_min']);
    $team = (string)require_body_field($body, 'user_team');
    if (!NflData::getTeam($team)) json_response(['detail' => 'Invalid team'], 400);
    $year = (int)($body['year'] ?? $cfg['app']['season_year']);
    $difficulty = in_array($body['difficulty'] ?? '', array_keys(SimEngine::DIFFICULTY_MULT), true) ? $body['difficulty'] : 'balanced';
    $id = uuidv4();
    $token = bin2hex(random_bytes(32));
    $schedule = Season::generateSchedule($id, 18);
    foreach ($schedule as &$g) $g['weather'] = Weather::roll($g['home']);
    unset($g);
    Rivalries::annotateSchedule($schedule);
    $stmt = db()->prepare('INSERT INTO seasons (id, owner_token, user_team, year, difficulty, schedule_json) VALUES (?,?,?,?,?,?)');
    $stmt->execute([$id, $token, $team, $year, $difficulty, json_encode($schedule)]);
    json_response([
        'id' => $id, 'owner_token' => $token, 'user_team' => $team, 'year' => $year,
        'difficulty' => $difficulty, 'schedule' => $schedule, 'current_week' => 1,
    ]);
}

// ---------- Season read ----------
if (preg_match('#^/api/season/([0-9a-f-]{36})$#i', $path, $m) && $method === 'GET') {
    $row = load_season($m[1]);
    $season = decode_season($row);
    unset($season['owner_token']);
    $season['standings'] = Season::computeStandings($season['schedule']);
    json_response($season);
}

// ---------- Sim a single game ----------
if ($path === '/api/season/sim-game' && $method === 'POST') {
    rate_limit('sim:' . client_ip(), $cfg['security']['sim_game_per_min']);
    $sid = (string)require_body_field($body, 'season_id');
    $gid = (string)require_body_field($body, 'game_id');
    $row = require_owner_token($sid, owner_token_header());
    $s = decode_season($row);
    $idx = null;
    foreach ($s['schedule'] as $i => $g) if ($g['game_id'] === $gid) { $idx = $i; break; }
    if ($idx === null) json_response(['detail' => 'Game not found'], 404);
    $game = $s['schedule'][$idx];
    if ($game['played']) json_response(['detail' => 'Game already played'], 400);

    $result = SimEngine::simulateFullGame($game['home'], $game['away'], [
        'weather'       => $game['weather'] ?? 'CLEAR',
        'depth_charts'  => $s['depth_charts'] ?? [],
        'injuries'      => $s['injuries'] ?? [],
        'difficulty'    => $s['difficulty'] ?? 'balanced',
        'coaching'      => $s['coaching'] ?? [],
        'playbook'      => $s['playbook'] ?? [],
        'rivalry'       => !empty($game['rivalry']),
        'deck_config'   => $s['deck_config'] ?? null,
        'current_week'  => $s['current_week'] ?? 1,
    ]);

    // Persist score + log
    $s['schedule'][$idx]['played']      = true;
    $s['schedule'][$idx]['home_score']  = $result['home_score'];
    $s['schedule'][$idx]['away_score']  = $result['away_score'];
    // Store log
    $logId = uuidv4();
    $lg = db()->prepare('INSERT INTO game_logs (id, season_id, game_id, home_team, away_team, home_score, away_score, plays_json, box_json) VALUES (?,?,?,?,?,?,?,?,?)');
    $lg->execute([
        $logId, $sid, $gid, $result['home_id'], $result['away_id'],
        $result['home_score'], $result['away_score'],
        json_encode($result['plays']),
        json_encode(['player_stats' => $result['player_stats'], 'weather' => $result['weather']]),
    ]);
    $s['schedule'][$idx]['log_id'] = $logId;

    // Record any new injuries (starter concussions from card + regular game injuries)
    $injuriesDb = $s['injuries'] ?? [];
    foreach ($result['new_injuries'] as $team => $list) {
        foreach ($list as $inj) {
            $injuriesDb[$team][] = $inj + ['injured_week' => $s['current_week'] ?? 1];
        }
    }

    $upd = db()->prepare('UPDATE seasons SET schedule_json = ?, injuries_json = ? WHERE id = ?');
    $upd->execute([json_encode($s['schedule']), json_encode($injuriesDb), $sid]);

    json_response(['ok' => true, 'log_id' => $logId, 'result' => $result]);
}

// ---------- Sim an entire week ----------
if ($path === '/api/season/sim-week' && $method === 'POST') {
    rate_limit('week:' . client_ip(), 30);
    $sid = (string)require_body_field($body, 'season_id');
    $row = require_owner_token($sid, owner_token_header());
    $s = decode_season($row);
    $week = $s['current_week'] ?? 1;
    $simmed = 0;
    foreach ($s['schedule'] as $i => $g) {
        if ($g['week'] !== $week || $g['played']) continue;
        $result = SimEngine::simulateFullGame($g['home'], $g['away'], [
            'weather'      => $g['weather'] ?? 'CLEAR',
            'depth_charts' => $s['depth_charts'] ?? [],
            'injuries'     => $s['injuries'] ?? [],
            'difficulty'   => $s['difficulty'] ?? 'balanced',
            'coaching'     => $s['coaching'] ?? [],
            'playbook'     => $s['playbook'] ?? [],
            'rivalry'      => !empty($g['rivalry']),
            'deck_config'  => $s['deck_config'] ?? null,
            'current_week' => $week,
            'allow_ot'     => false,
        ]);
        $s['schedule'][$i]['played'] = true;
        $s['schedule'][$i]['home_score'] = $result['home_score'];
        $s['schedule'][$i]['away_score'] = $result['away_score'];
        $simmed++;
    }
    $s['current_week'] = min(18, $week + 1);
    db()->prepare('UPDATE seasons SET schedule_json = ?, current_week = ? WHERE id = ?')
        ->execute([json_encode($s['schedule']), $s['current_week'], $sid]);
    json_response(['ok' => true, 'simmed' => $simmed, 'current_week' => $s['current_week']]);
}

// ---------- Game log ----------
if (preg_match('#^/api/game-log/([0-9a-f-]{36})$#i', $path, $m) && $method === 'GET') {
    $stmt = db()->prepare('SELECT * FROM game_logs WHERE id = ?');
    $stmt->execute([$m[1]]);
    $log = $stmt->fetch();
    if (!$log) json_response(['detail' => 'Log not found'], 404);
    $log['plays'] = json_decode($log['plays_json'], true);
    $log['box']   = $log['box_json'] ? json_decode($log['box_json'], true) : null;
    unset($log['plays_json'], $log['box_json']);
    json_response($log);
}

// ---------- Coaching / Playbook / Difficulty / Trade / Rival ----------
if ($path === '/api/season/coaching' && $method === 'POST') {
    $sid = (string)require_body_field($body, 'season_id');
    $team = (string)require_body_field($body, 'team');
    $phil = (string)require_body_field($body, 'philosophy');
    if (!in_array($phil, ['aggressive', 'balanced', 'conservative'], true)) json_response(['detail' => 'Invalid philosophy'], 400);
    if (!NflData::getTeam($team)) json_response(['detail' => 'Invalid team'], 400);
    $row = require_owner_token($sid, owner_token_header());
    $s = decode_season($row);
    $coaching = $s['coaching'] ?? [];
    $coaching[$team] = $phil;
    db()->prepare('UPDATE seasons SET coaching_json = ? WHERE id = ?')->execute([json_encode($coaching), $sid]);
    json_response(['coaching' => $coaching]);
}

if ($path === '/api/season/playbook' && $method === 'POST') {
    $sid = (string)require_body_field($body, 'season_id');
    $team = (string)require_body_field($body, 'team');
    $bias = (float)require_body_field($body, 'pass_bias');
    if (!NflData::getTeam($team)) json_response(['detail' => 'Invalid team'], 400);
    $bias = max(-0.25, min(0.25, $bias));
    $row = require_owner_token($sid, owner_token_header());
    $s = decode_season($row);
    $pb = $s['playbook'] ?? [];
    $pb[$team] = $bias;
    db()->prepare('UPDATE seasons SET playbook_json = ? WHERE id = ?')->execute([json_encode($pb), $sid]);
    json_response(['playbook' => $pb]);
}

// ---------- Custom Flippy Deck ----------
if (preg_match('#^/api/season/([0-9a-f-]{36})/deck-config$#i', $path, $m) && $method === 'GET') {
    $row = load_season($m[1]);
    $s = decode_season($row);
    $history = $s['champions_history'] ?? [];
    $unlocked = count($history) >= 2 || ($s['year'] ?? 2025) >= 2027;
    json_response([
        'config'   => $s['deck_config'] ?? ['preset' => 'balanced', 'signature_cards' => []],
        'unlocked' => $unlocked,
        'year'     => $s['year'] ?? 2025,
        'seasons_played' => count($history),
        'presets'  => array_keys(FlippyDeck::presets()),
        'max_signature_cards' => FlippyDeck::MAX_SIGNATURE_CARDS,
        'max_copies_per_card' => FlippyDeck::MAX_SIGNATURE_COPIES,
    ]);
}
if ($path === '/api/season/deck-config' && $method === 'POST') {
    $sid = (string)require_body_field($body, 'season_id');
    $row = require_owner_token($sid, owner_token_header());
    $s = decode_season($row);
    $history = $s['champions_history'] ?? [];
    $unlocked = count($history) >= 2 || ($s['year'] ?? 2025) >= 2027;
    if (!$unlocked) json_response(['detail' => 'Custom Flippy Deck unlocks after 2 completed seasons'], 403);
    $preset = strtolower($body['preset'] ?? 'balanced');
    if (!array_key_exists($preset, FlippyDeck::presets())) json_response(['detail' => 'Invalid preset'], 400);
    // Reuse the class's own sanitizer by round-tripping through the constructor
    $tmpDeck = FlippyDeck::newDeck(['preset' => $preset, 'signature_cards' => $body['signature_cards'] ?? []]);
    $cleanSignature = [];
    foreach (($tmpDeck['config']['signature_cards'] ?? []) as $c) $cleanSignature[] = $c;
    // The constructor doesn't return sanitized list separately; do a second pass via manual sanitize logic:
    // We re-sanitize here for the response payload.
    $signature = [];
    foreach (array_slice((array)($body['signature_cards'] ?? []), 0, FlippyDeck::MAX_SIGNATURE_CARDS) as $c) {
        if (!is_array($c)) continue;
        $y = max(-10, min(60, (int)($c['yards'] ?? 0)));
        $cnt = max(1, min(FlippyDeck::MAX_SIGNATURE_COPIES, (int)($c['count'] ?? 1)));
        $label = (string)($c['label'] ?? "Signature $y");
        if (strlen($label) > 24) $label = substr($label, 0, 24);
        $signature[] = ['label' => $label, 'yards' => $y, 'count' => $cnt];
    }
    $config = ['preset' => $preset, 'signature_cards' => $signature];
    db()->prepare('UPDATE seasons SET deck_config_json = ? WHERE id = ?')->execute([json_encode($config), $sid]);
    json_response(['config' => $config]);
}

// ---------- Playoffs ----------
if (preg_match('#^/api/season/([0-9a-f-]{36})/start-playoffs$#i', $path, $m) && $method === 'POST') {
    $sid = $m[1];
    $row = require_owner_token($sid, owner_token_header());
    $s = decode_season($row);
    $standings = Season::computeStandings($s['schedule']);
    $seeds = Playoffs::seedPlayoffs($standings);
    $bracket = Playoffs::buildBracket($seeds);
    db()->prepare('UPDATE seasons SET playoffs_json = ? WHERE id = ?')->execute([json_encode($bracket), $sid]);
    json_response(['playoffs' => $bracket]);
}

// ---------- Team rivals ----------
if (preg_match('#^/api/season/([0-9a-f-]{36})/rivals/([A-Z]{2,3})$#', $path, $m) && $method === 'GET') {
    $s = decode_season(load_season($m[1]));
    $legacy = [];
    foreach (Rivalries::LEGACY as $l) if (in_array($m[2], $l, true)) $legacy[] = $l;
    json_response([
        'division_rivals' => Rivalries::divisionRivals($m[2]),
        'legacy_rivals'   => $legacy,
        'extra_rival'     => ($s['extra_rivals'] ?? [])[$m[2]] ?? null,
    ]);
}

// ---------- 404 ----------
json_response(['detail' => 'Not found: ' . $method . ' ' . $path], 404);
