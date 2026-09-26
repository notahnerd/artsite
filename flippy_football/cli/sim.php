<?php
/**
 * CLI runner — sim a single game between any two teams.
 * Usage:
 *   php cli/sim.php KC BUF                        # balanced arcade
 *   php cli/sim.php PHI SF --difficulty=hard --rivalry
 *   php cli/sim.php KC BUF --deck-preset=chaos    # custom Flippy Deck
 */
require __DIR__ . '/../includes/sim_engine.php';

$argsRaw = array_slice($argv, 1);
$home = null; $away = null;
$flags = [];
foreach ($argsRaw as $a) {
    if (str_starts_with($a, '--')) {
        $eq = strpos($a, '=');
        if ($eq !== false) $flags[substr($a, 2, $eq - 2)] = substr($a, $eq + 1);
        else $flags[substr($a, 2)] = true;
    } elseif ($home === null) $home = $a;
    elseif ($away === null) $away = $a;
}
if (!$home || !$away) {
    fwrite(STDERR, "Usage: php cli/sim.php HOME_TEAM AWAY_TEAM [--difficulty=hard] [--rivalry] [--deck-preset=chaos]\n");
    exit(2);
}

$opts = [
    'difficulty' => $flags['difficulty'] ?? 'arcade',
    'rivalry'    => !empty($flags['rivalry']),
    'weather'    => $flags['weather'] ?? 'CLEAR',
];
if (!empty($flags['deck-preset'])) {
    $opts['deck_config'] = ['preset' => $flags['deck-preset'], 'signature_cards' => []];
}

$g = SimEngine::simulateFullGame($home, $away, $opts);
printf("=== %s @ %s === %s\n", $away, $home, $opts['rivalry'] ? '(RIVALRY)' : '');
printf("Final: %s %d - %d %s   (Q%d%s)\n",
    $g['home_id'], $g['home_score'], $g['away_score'], $g['away_id'],
    $g['quarter'], $g['ot'] ? ' OT' : '');
printf("Plays: %d\n\n", count($g['plays']));
$cards = 0; $overrides = 0; $inj = 0;
foreach ($g['plays'] as $p) {
    if (isset($p['card'])) $cards++;
    if (!empty($p['card_override'])) $overrides++;
    if (($p['card']['type'] ?? '') === 'INJURY') $inj++;
}
printf("Cards drawn: %d, yardage overrides: %d, injury cards: %d\n\n", $cards, $overrides, $inj);
echo "First 12 plays:\n";
foreach (array_slice($g['plays'], 0, 12) as $p) {
    printf("  Q%d %-5s → %s\n", $p['quarter'], $p['play_type'], $p['description']);
}
