<?php
/**
 * Tabletop simulation engine — 3d6 (chart index) + 1 red die (offense/defense read).
 * Ported from sim_engine.py.
 *
 * Every RUN/PASS play draws a card from the Flippy Deck:
 *  - DICE  → resolve via dice chart (default)
 *  - YARDS → override with card yards (skips fumble roll)
 *  - INJURY → concussion applied to a random offensive starter, then dice runs
 */
require_once __DIR__ . '/nfl_data.php';
require_once __DIR__ . '/player_cards.php';
require_once __DIR__ . '/flippy_deck.php';
require_once __DIR__ . '/weather.php';
require_once __DIR__ . '/injuries.php';

final class SimEngine {
    public const DIFFICULTY_MULT = [
        'easy'       => 1.15, 'balanced' => 1.0, 'hard' => 0.88,
        'legendary'  => 0.78, 'arcade'   => 1.05,
    ];

    // ---------- Roll dice ----------
    public static function rollDice(): array {
        $white = [mt_rand(1, 6), mt_rand(1, 6), mt_rand(1, 6)];
        $red   = mt_rand(1, 6);
        return ['white' => $white, 'red' => $red, 'sum' => array_sum($white), 'read' => $red <= 3 ? 'OFF' : 'DEF'];
    }

    // ---------- Play choice ----------
    public static function choosePlayType(int $down, int $distance, int $ballOn, int $scoreDiff,
                                          int $quarter, int $clock, string $philosophy = 'balanced',
                                          float $passBias = 0.0): string {
        $yardsToGoal = 100 - $ballOn;
        $aggro = ['aggressive' => 0.15, 'balanced' => 0.0, 'conservative' => -0.10][$philosophy] ?? 0.0;
        if ($down === 4) {
            $goForItBoost = ['aggressive' => 15, 'balanced' => 0, 'conservative' => -8][$philosophy] ?? 0;
            if ($yardsToGoal <= 35 - intdiv($goForItBoost, 3)) return 'FG';
            $threshDist = 2 + ($philosophy === 'aggressive' ? 2 : 0);
            $threshBall = 55 - ($philosophy === 'aggressive' ? 10 : 0);
            if ($distance <= $threshDist && $ballOn > $threshBall) {
                return (mt_rand() / mt_getrandmax()) < 0.5 ? 'RUN' : 'PASS';
            }
            return 'PUNT';
        }
        $passProb = 0.55 + $aggro + $passBias;
        if ($scoreDiff < -7 && $quarter >= 3) $passProb = 0.82 + $aggro / 2 + $passBias;
        if ($distance >= 8) $passProb = min(0.90, $passProb + 0.15);
        if ($distance <= 2) $passProb = 0.35 + $aggro + $passBias;
        return (mt_rand() / mt_getrandmax()) < max(0.15, min(0.9, $passProb)) ? 'PASS' : 'RUN';
    }

    // ---------- Full game ----------
    public static function simulateFullGame(string $homeId, string $awayId, array $opts = []): array {
        $weatherCode  = $opts['weather']       ?? 'CLEAR';
        $depthCharts  = $opts['depth_charts']  ?? [];
        $injuriesData = $opts['injuries']      ?? [];
        $difficulty   = $opts['difficulty']    ?? 'arcade';
        $allowOT      = $opts['allow_ot']      ?? true;
        $rollInjuries = $opts['roll_injuries'] ?? true;
        $customRosters= $opts['custom_rosters']?? [];
        $coaching     = $opts['coaching']      ?? [];
        $playbook     = $opts['playbook']      ?? [];
        $rivalry      = $opts['rivalry']       ?? false;
        $deckConfig   = $opts['deck_config']   ?? null;
        $currentWeek  = $opts['current_week']  ?? 1;

        $home = NflData::getTeam($homeId);
        $away = NflData::getTeam($awayId);
        if (!$home || !$away) throw new RuntimeException("Invalid team id");

        // Build player rosters (respect custom rosters + injuries)
        $homePlayers = self::rosterFor($homeId, $customRosters, $depthCharts);
        $awayPlayers = self::rosterFor($awayId, $customRosters, $depthCharts);
        // Mark active-injuries as injured=true so they aren't starters
        $homeInjured = Injuries::injuredNames($injuriesData[$homeId] ?? [], $currentWeek);
        $awayInjured = Injuries::injuredNames($injuriesData[$awayId] ?? [], $currentWeek);
        foreach ($homePlayers as &$p) if (in_array($p['name'], $homeInjured, true)) { $p['starter'] = false; $p['injured'] = true; }
        unset($p);
        foreach ($awayPlayers as &$p) if (in_array($p['name'], $awayInjured, true)) { $p['starter'] = false; $p['injured'] = true; }
        unset($p);
        // Promote a backup at each pos if the starter is out
        self::promoteBackups($homePlayers);
        self::promoteBackups($awayPlayers);

        $players = [$homeId => $homePlayers, $awayId => $awayPlayers];

        $diffMult = self::DIFFICULTY_MULT[$difficulty] ?? 1.0;
        // Rivalry: +7% aggression / big-play
        $rivalryMult = $rivalry ? 1.08 : 1.0;

        $state = self::newGameState($homeId, $awayId);
        $state['weather'] = $weatherCode;
        $state['deck'] = FlippyDeck::newDeck($deckConfig);        $plays = [];
        $scoreLog = [];
        $pstats = [$homeId => [], $awayId => []];
        $newInjuries = [$homeId => [], $awayId => []];

        // Coin flip: home gets ball first (simplification — real games alternate)
        $possession = $homeId;
        $ballOn = 25; $down = 1; $distance = 10;

        while (!self::isGameOver($state, $allowOT)) {
            $offId = $possession;
            $defId = $offId === $homeId ? $awayId : $homeId;
            $phil = $coaching[$offId] ?? 'balanced';
            $passBias = (float)($playbook[$offId] ?? 0);

            $scoreDiff = $state['score'][$offId] - $state['score'][$defId];
            $playType = self::choosePlayType($down, $distance, $ballOn, $scoreDiff, $state['quarter'], $state['clock'], $phil, $passBias);

            [$event, $possessionChanged, $newBallOn, $newDown, $newDistance] = self::simulatePlay(
                $state, $offId, $defId, $players, $ballOn, $down, $distance,
                $playType, $weatherCode, $diffMult, $rivalryMult, $pstats
            );
            $plays[] = $event;

            if ($event['score_change'] !== null) {
                $scoreLog[] = $event['score_change'];
            }

            // Clock: 30s off for run, 25s for incomplete, 40s otherwise; FG/PUNT ~10s
            $tick = match ($event['play_type']) {
                'RUN' => 30,
                'PASS' => ($event['result'] === 'INCOMPLETE' || $event['result'] === 'DROP') ? 15 : 32,
                'PUNT', 'FG' => 10,
                default => 30,
            };
            $state['clock'] -= $tick;
            if ($state['clock'] <= 0) {
                self::advanceQuarter($state);
            }

            $ballOn = $newBallOn;
            $down = $newDown;
            $distance = $newDistance;
            if ($possessionChanged) {
                $possession = $defId;
                $ballOn = 100 - $ballOn;
                $down = 1;
                $distance = 10;
            }
        }

        // Roll new injuries after game (per-starter chance)
        if ($rollInjuries) {
            $newInjuries[$homeId] = Injuries::rollForGame($homePlayers);
            $newInjuries[$awayId] = Injuries::rollForGame($awayPlayers);
        }

        return [
            'home_id'   => $homeId,
            'away_id'   => $awayId,
            'home_score'=> $state['score'][$homeId],
            'away_score'=> $state['score'][$awayId],
            'plays'     => $plays,
            'quarter'   => $state['quarter'],
            'weather'   => $weatherCode,
            'player_stats' => $pstats,
            'new_injuries' => $newInjuries,
            'ot'        => $state['quarter'] > 4,
        ];
    }

    // ---------- Play resolution ----------
    private static function simulatePlay(array &$state, string $offId, string $defId, array &$players,
                                         int $ballOn, int $down, int $distance,
                                         string $playType, string $weatherCode,
                                         float $diffMult, float $rivalryMult, array &$pstats): array {
        $offPlayers = &$players[$offId];
        $defTeam = NflData::getTeam($defId);

        $dice = self::rollDice();
        $rollSum = $dice['sum'];
        $read = $dice['read'];

        $event = [
            'quarter' => $state['quarter'], 'clock' => $state['clock'],
            'off' => $offId, 'def' => $defId,
            'down' => $down, 'distance' => $distance, 'ball_on' => $ballOn,
            'play_type' => $playType,
            'dice' => $dice['white'], 'red' => $dice['red'], 'read' => $read, 'roll' => $rollSum,
            'yards' => 0, 'result' => 'NORMAL', 'description' => '',
            'score_change' => null, 'turnover' => false,
        ];

        // ---- FG ----
        if ($playType === 'FG') {
            $k = self::firstStarter($offPlayers, 'K') ?? ['name' => 'Kicker', 'ovr' => 78];
            $dist = (100 - $ballOn) + 17;
            [$chart, $maxRange] = PlayerCards::kCard($k);
            $cell = $chart[$rollSum];
            $wpen = Weather::info($weatherCode)['fg_penalty'];
            $effRange = $maxRange + $cell['range_bonus'] - $wpen;
            $good = $cell['make'] !== false && $dist <= $effRange;
            if ($cell['make'] === true) $good = true;
            if ($cell['make'] === false) $good = false;
            $event['result'] = $good ? 'FG_GOOD' : 'FG_MISS';
            $event['description'] = ($good ? "$dist-yd FG by {$k['name']} is GOOD! ({$cell['result']})"
                                           : "$dist-yd FG by {$k['name']} is NO GOOD ({$cell['result']}).");
            if ($good) {
                $state['score'][$offId] += 3;
                $event['score_change'] = ['team' => $offId, 'points' => 3, 'type' => 'FG'];
                return [$event, true, 25, 1, 10];
            }
            return [$event, true, $ballOn, 1, 10];
        }
        // ---- PUNT ----
        if ($playType === 'PUNT') {
            $puntNet = 40 + mt_rand(-8, 12);
            $event['result'] = 'PUNT';
            $event['description'] = "Punt netted $puntNet yds.";
            $newBallOn = min(100, $ballOn + $puntNet);
            if ($newBallOn >= 100) $newBallOn = 80;   // touchback → other 20
            return [$event, true, $newBallOn, 1, 10];
        }

        // ---------- Flippy Deck pre-snap flip ----------
        $flippy = null;
        $deck = &$state['deck'];
        if ($deck !== null) {
            $flippy = FlippyDeck::drawFrom($deck);
            $flippy['remaining'] = FlippyDeck::remainingIn($deck);
            $event['card'] = $flippy;
            if ($flippy['type'] === 'INJURY') {
                $victim = FlippyDeck::pickInjuryTarget($offPlayers);
                if ($victim) {
                    $inj = FlippyDeck::applyConcussion($offPlayers, $victim);
                    $flippy['injury'] = $inj;
                    $event['card'] = $flippy;
                }
            }
        }

        // ---------- Dice resolution ----------
        $qb = self::firstStarter($offPlayers, 'QB');
        $rb = self::firstStarter($offPlayers, 'RB');
        if ($playType === 'RUN') {
            if (!$rb) $rb = ['name' => 'Backup RB', 'ovr' => 70, 'pos' => 'RB'];
            [$yards, $result] = PlayerCards::resolveRun($rb, $defTeam, $rollSum, $read);
            if ($yards > 0) $yards = (int)round($yards * $diffMult * $rivalryMult);
            self::addStat($pstats, $offId, $rb['name'], 'carries', 1);
        } else { // PASS
            if (!$qb) $qb = ['name' => 'Backup QB', 'ovr' => 70, 'pos' => 'QB'];
            $wr = self::pickReceiver($offPlayers);
            [$yards, $result] = PlayerCards::resolvePass($qb, $wr, $defTeam, $rollSum, $read);
            [$yards, $result] = self::applyWeatherPass($yards, $result, $weatherCode);
            if ($yards > 0) $yards = (int)round($yards * $diffMult * $rivalryMult);
            $event['target'] = $wr['name'];
            self::addStat($pstats, $offId, $wr['name'], 'targets', 1);
        }

        // ---------- YARDS card override ----------
        if ($flippy && $flippy['type'] === 'YARDS') {
            $yards = $flippy['yards'];
            $result = $playType === 'RUN' ? 'NORMAL' : ($yards === 0 ? 'INCOMPLETE' : 'NORMAL');
            $event['card_override'] = true;
        }

        // ---------- Handle turnover early exits ----------
        if ($result === 'INT') {
            $event['result'] = 'INT'; $event['yards'] = 0; $event['turnover'] = true;
            $event['description'] = ($qb['name'] ?? 'QB') . "'s pass is INTERCEPTED!";
            self::addStat($pstats, $offId, $qb['name'] ?? 'QB', 'ints', 1);
            return [$event, true, max(20, 100 - $ballOn - 10), 1, 10];
        }
        if ($result === 'INT_RETURN_TD') {
            $event['result'] = 'INT_RETURN_TD'; $event['yards'] = 0; $event['turnover'] = true;
            $state['score'][$defId] += 7;
            $event['score_change'] = ['team' => $defId, 'points' => 7, 'type' => 'DEF_TD'];
            $event['description'] = "PICK-SIX by the defense!";
            return [$event, true, 25, 1, 10];
        }
        if ($result === 'FUMBLE') {
            $event['result'] = 'FUMBLE'; $event['yards'] = $yards; $event['turnover'] = true;
            $event['description'] = "Ball is on the ground — FUMBLE recovered by defense!";
            return [$event, true, max(20, 100 - $ballOn - $yards), 1, 10];
        }
        // Random fumble on positive-yard normal plays (skip on card override)
        if ($result === 'NORMAL' && $yards > 0 && empty($event['card_override']) && (mt_rand() / mt_getrandmax()) < 0.03) {
            $event['result'] = 'FUMBLE'; $event['yards'] = $yards; $event['turnover'] = true;
            $event['description'] = "Big play — but the ball comes loose! FUMBLE!";
            return [$event, true, max(20, 100 - $ballOn - $yards), 1, 10];
        }

        // ---------- Ball position + TD check ----------
        $newBallOn = $ballOn + $yards;
        $isTd = $newBallOn >= 100;
        if ($isTd) {
            $newBallOn = 100;
            $state['score'][$offId] += 7;
            $event['score_change'] = ['team' => $offId, 'points' => 7, 'type' => 'TD'];
            $event['result'] = 'TD';
            $event['yards'] = $yards;
            $narr = $playType === 'RUN'
                ? "TD RUN by " . ($rb['name'] ?? '?') . " for $yards yds!"
                : "TD PASS from " . ($qb['name'] ?? '?') . " to " . ($wr['name'] ?? '?') . " for $yards yds!";
            $event['description'] = $narr;
            if ($playType === 'RUN')  self::addStat($pstats, $offId, $rb['name'] ?? '?', 'rush_tds', 1);
            else                       self::addStat($pstats, $offId, $wr['name'] ?? '?', 'rec_tds', 1);
            return [$event, true, 25, 1, 10];
        }

        // ---------- Result narration + down/distance ----------
        $event['yards'] = $yards;
        if ($result === 'INCOMPLETE' || $result === 'DROP') {
            $event['result'] = $result;
            $event['description'] = ($qb['name'] ?? 'QB') . "'s pass falls INCOMPLETE"
                . ($result === 'DROP' ? " — dropped by " . ($wr['name'] ?? '?') : '') . ".";
            $yards = 0;
        } elseif ($result === 'SACK') {
            $event['result'] = 'SACK';
            $event['description'] = "SACK for " . abs($yards) . " yds.";
            self::addStat($pstats, $offId, $qb['name'] ?? '?', 'sacks_taken', 1);
        } elseif ($result === 'TFL' || $result === 'STUFF') {
            $event['result'] = $result;
            $event['description'] = ($rb['name'] ?? '?') . " stopped for a loss of " . abs($yards) . " yds.";
        } elseif ($result === 'HURRY') {
            $event['result'] = 'HURRY';
            $event['description'] = "QB pressured — throwaway. -" . abs($yards) . " yds.";
        } else {
            // NORMAL / BIG_PLAY / BREAKAWAY / DEEP_BOMB / COVERAGE_BUST
            $event['result'] = ($yards >= 20 ? ($result === 'NORMAL' ? 'BIG_PLAY' : $result) : $result);
            if ($playType === 'RUN') {
                $event['description'] = ($rb['name'] ?? '?') . " runs for " . ($yards >= 0 ? "+$yards" : $yards) . " yds.";
                self::addStat($pstats, $offId, $rb['name'] ?? '?', 'rush_yards', $yards);
            } else {
                $event['description'] = ($qb['name'] ?? '?') . " → " . ($wr['name'] ?? '?') . " for " . ($yards >= 0 ? "+$yards" : $yards) . " yds.";
                self::addStat($pstats, $offId, $qb['name'] ?? '?', 'pass_yards', $yards);
                self::addStat($pstats, $offId, $wr['name'] ?? '?', 'rec_yards', $yards);
                self::addStat($pstats, $offId, $wr['name'] ?? '?', 'catches', 1);
            }
        }

        // Down/distance advance
        $gained = $yards;
        if ($gained >= $distance) {
            return [$event, false, $newBallOn, 1, 10];   // first down
        }
        if ($down >= 4) {
            return [$event, true, 100 - $newBallOn, 1, 10];  // turnover on downs
        }
        return [$event, false, $newBallOn, $down + 1, $distance - $gained];
    }

    // ---------- Weather ----------
    private static function applyWeatherPass(int $yards, string $result, string $code): array {
        $w = Weather::info($code);
        if ($yards > 0 && $result !== 'INT' && $result !== 'SACK') {
            $yards = (int)round($yards * $w['pass_mult']);
        }
        if (in_array($result, ['BIG_PLAY', 'DEEP_BOMB', 'COVERAGE_BUST'], true) && $yards > 0) {
            $yards = (int)round($yards * $w['big_play_mult']);
        }
        // Random INT bump (weather int_bonus)
        if ($result === 'NORMAL' && $w['int_bonus'] > 0 && (mt_rand() / mt_getrandmax()) < 0.02 * $w['int_bonus']) {
            return [0, 'INT'];
        }
        return [$yards, $result];
    }

    // ---------- Rosters ----------
    private static function rosterFor(string $teamId, array $customRosters, array $depthCharts): array {
        if (isset($customRosters[$teamId])) return $customRosters[$teamId];
        $players = NflData::getPlayers($teamId);
        // Depth-chart override: array of ['name' => ..., 'pos' => ...] declared as starters
        if (!empty($depthCharts[$teamId])) {
            $starterNames = array_column($depthCharts[$teamId], 'name');
            foreach ($players as &$p) $p['starter'] = in_array($p['name'], $starterNames, true);
            unset($p);
        }
        return $players;
    }

    private static function promoteBackups(array &$players): void {
        $posBuckets = [];
        foreach ($players as $p) $posBuckets[$p['pos'] ?? '?'][] = $p['name'];
        foreach (['QB', 'RB', 'WR', 'TE', 'K'] as $pos) {
            $hasStarter = false;
            foreach ($players as $p) if (($p['pos'] ?? '') === $pos && !empty($p['starter']) && empty($p['injured'])) { $hasStarter = true; break; }
            if ($hasStarter) continue;
            $best = null;
            foreach ($players as $p) if (($p['pos'] ?? '') === $pos && empty($p['injured'])) {
                if ($best === null || ($p['ovr'] ?? 0) > ($best['ovr'] ?? 0)) $best = $p;
            }
            if ($best) {
                foreach ($players as &$p) if ($p['name'] === $best['name']) { $p['starter'] = true; }
                unset($p);
            }
        }
    }

    private static function firstStarter(array $players, string $pos): ?array {
        foreach ($players as $p) {
            if (($p['pos'] ?? '') === $pos && !empty($p['starter']) && empty($p['injured'])) return $p;
        }
        return null;
    }

    private static function pickReceiver(array $players): array {
        $eligible = array_values(array_filter($players, static fn($p) =>
            in_array($p['pos'] ?? '', ['WR', 'TE', 'RB'], true) && !empty($p['starter']) && empty($p['injured'])
        ));
        if (empty($eligible)) return ['name' => 'Slot Receiver', 'pos' => 'WR', 'ovr' => 72];
        // Weight by OVR
        $weights = array_map(static fn($p) => max(1, ($p['ovr'] ?? 70) - 60), $eligible);
        $total = array_sum($weights);
        $r = mt_rand(1, $total);
        $cum = 0;
        foreach ($eligible as $i => $p) {
            $cum += $weights[$i];
            if ($r <= $cum) return $p;
        }
        return $eligible[0];
    }

    // ---------- State ----------
    private static function newGameState(string $homeId, string $awayId): array {
        return [
            'quarter' => 1,
            'clock'   => 15 * 60,       // seconds remaining in quarter
            'score'   => [$homeId => 0, $awayId => 0],
            'deck'    => null,
            'weather' => 'CLEAR',
        ];
    }

    private static function advanceQuarter(array &$state): void {
        $state['quarter']++;
        $state['clock'] = 15 * 60;
        if ($state['quarter'] === 5) $state['clock'] = 10 * 60;    // OT
    }

    private static function isGameOver(array $state, bool $allowOT): bool {
        if ($state['quarter'] < 4) return false;
        if ($state['quarter'] === 4 && $state['clock'] > 0) return false;
        // End of regulation
        $scores = array_values($state['score']);
        if ($state['quarter'] >= 4 && $scores[0] !== $scores[1]) return true;
        if (!$allowOT) return true;
        // OT
        return $state['quarter'] >= 5 && ($scores[0] !== $scores[1] || $state['clock'] <= 0);
    }

    // ---------- Stats ----------
    private static function addStat(array &$pstats, string $teamId, string $name, string $key, int $val): void {
        if (!isset($pstats[$teamId][$name])) $pstats[$teamId][$name] = ['name' => $name];
        $pstats[$teamId][$name][$key] = ($pstats[$teamId][$name][$key] ?? 0) + $val;
    }
}
