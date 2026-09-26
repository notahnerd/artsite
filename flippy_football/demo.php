<?php
/**
 * CLI demo — runs a quick simulation of the Flippy Deck.
 *
 * Usage:
 *   php demo.php                                   # balanced deck, 350 draws
 *   php demo.php --preset=chaos --draws=500        # chaos + 500 draws (forces a reshuffle)
 *   php demo.php --preset=air_raid --sig           # air_raid with signature cards
 */

require __DIR__ . '/flippy_deck.php';

// ---------- args ----------
$opts = getopt('', ['preset::', 'draws::', 'sig']);
$preset = $opts['preset'] ?? 'balanced';
$draws  = isset($opts['draws']) ? (int)$opts['draws'] : FlippyDeck::DECK_SIZE;

$signatureCards = [];
if (isset($opts['sig'])) {
    $signatureCards = [
        ['label' => 'Arrowhead Roar', 'yards' => 25, 'count' => 3],
        ['label' => 'Fumbleroski',    'yards' => -8, 'count' => 2],
        ['label' => 'Philly Special', 'yards' => 45, 'count' => 1],
    ];
}

// ---------- build deck ----------
$config = ['preset' => $preset, 'signature_cards' => $signatureCards];
$deck   = new FlippyDeck($config);

echo "=== Flippy Deck Demo ===\n";
echo "Preset: {$preset}\n";
echo "Signature cards: " . count($signatureCards) . "\n";
echo "Draws requested: {$draws}\n";
echo "Deck size: " . $deck->remaining() . "\n\n";

// ---------- draw loop ----------
$counts = ['DICE' => 0, 'YARDS' => 0, 'INJURY' => 0, 'SIGNATURE' => 0];
$yards  = [];
$signatureHits = [];

for ($i = 1; $i <= $draws; $i++) {
    $card = $deck->draw();
    $counts[$card['type']]++;
    if ($card['type'] === 'YARDS') {
        $yards[] = $card['yards'];
        if (!empty($card['signature'])) {
            $counts['SIGNATURE']++;
            $signatureHits[$card['label']] = ($signatureHits[$card['label']] ?? 0) + 1;
        }
    }
    if ($i <= 8 || $card['type'] !== 'DICE') {
        printf(
            "  #%3d  %-9s %s\n",
            $i,
            $card['type'],
            match($card['type']) {
                'DICE'   => '"Roll the dice"',
                'YARDS'  => sprintf(
                    '%+d yd%s',
                    $card['yards'],
                    !empty($card['signature']) ? ' ✨ '.$card['label'] : ''
                ),
                'INJURY' => '🚑 Concussion (random offensive starter)',
                default  => '',
            }
        );
    }
    if ($i === 9) echo "  ... (further DICE draws suppressed) ...\n";
}

echo "\n=== Summary ===\n";
foreach (['DICE', 'YARDS', 'INJURY', 'SIGNATURE'] as $t) {
    $pct = $draws > 0 ? number_format(100 * $counts[$t] / $draws, 1) : '0';
    printf("  %-9s  %4d  (%s%%)\n", $t, $counts[$t], $pct);
}
if (!empty($yards)) {
    printf("  YARDS range   min %d  max %d  avg %.2f\n", min($yards), max($yards), array_sum($yards) / count($yards));
}
if ($deck->reshuffles > 0) {
    echo "  Reshuffles:   {$deck->reshuffles}\n";
}
if (!empty($signatureHits)) {
    echo "\n  Signature draws:\n";
    foreach ($signatureHits as $label => $n) {
        printf("    %-24s ×%d\n", $label, $n);
    }
}

// ---------- injury demo ----------
echo "\n=== Injury demo (using the two INJURY cards over 5 offenses) ===\n";
$roster = [
    ['name' => 'Patrick Mahomes',   'pos' => 'QB', 'starter' => true,  'ovr' => 99],
    ['name' => 'Blaine Gabbert',    'pos' => 'QB', 'starter' => false, 'ovr' => 70],
    ['name' => 'Isiah Pacheco',     'pos' => 'RB', 'starter' => true,  'ovr' => 84],
    ['name' => 'Clyde Edwards-H',   'pos' => 'RB', 'starter' => false, 'ovr' => 78],
    ['name' => 'Travis Kelce',      'pos' => 'TE', 'starter' => true,  'ovr' => 96],
    ['name' => 'Rashee Rice',       'pos' => 'WR', 'starter' => true,  'ovr' => 82],
    ['name' => 'Marquise Brown',    'pos' => 'WR', 'starter' => true,  'ovr' => 81],
    ['name' => 'Justin Watson',     'pos' => 'WR', 'starter' => false, 'ovr' => 72],
    ['name' => 'Harrison Butker',   'pos' => 'K',  'starter' => true,  'ovr' => 90],
];
for ($j = 1; $j <= 5; $j++) {
    $v = FlippyDeck::pickInjuryTarget($roster);
    if (!$v) { echo "  No healthy starters left.\n"; break; }
    $inj = FlippyDeck::applyConcussion($roster, $v);
    printf("  Round %d: %-18s (%s) OUT ↦ replaced by %s\n",
        $j, $inj['player'], $inj['pos'], $inj['replacement'] ?? '(no backup)');
}

echo "\nDone.\n";
