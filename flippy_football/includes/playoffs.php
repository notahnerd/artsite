<?php
final class Playoffs {
    public const ROUND_LABELS = [
        'WC'   => 'Wild Card',
        'DIV'  => 'Divisional',
        'CONF' => 'Conference Championship',
        'SB'   => 'Super Bowl',
    ];

    /** Top 7 seeds per conference. */
    public static function seedPlayoffs(array $standings): array {
        $afc = array_slice(array_values(array_filter($standings, static fn($s) => $s['conf'] === 'AFC')), 0, 7);
        $nfc = array_slice(array_values(array_filter($standings, static fn($s) => $s['conf'] === 'NFC')), 0, 7);
        foreach ($afc as $i => &$t) $t['seed'] = $i + 1;
        unset($t);
        foreach ($nfc as $i => &$t) $t['seed'] = $i + 1;
        unset($t);
        return ['AFC' => $afc, 'NFC' => $nfc];
    }

    public static function buildBracket(array $seeds): array {
        $games = [];
        foreach (['AFC', 'NFC'] as $conf) {
            $s = $seeds[$conf];
            $pairs = [[1, 6], [2, 5], [3, 4]];
            foreach ($pairs as $idx => [$hi, $lo]) {
                $games[] = [
                    'id' => "{$conf}-WC-" . ($idx + 1),
                    'round' => 'WC', 'conf' => $conf,
                    'home' => $s[$hi]['team'], 'away' => $s[$lo]['team'],
                    'home_seed' => $s[$hi]['seed'], 'away_seed' => $s[$lo]['seed'],
                    'played' => false, 'home_score' => null, 'away_score' => null,
                    'winner' => null, 'log_id' => null,
                ];
            }
        }
        foreach (['AFC', 'NFC'] as $conf) {
            for ($i = 0; $i < 2; $i++) {
                $games[] = [
                    'id' => "{$conf}-DIV-" . ($i + 1), 'round' => 'DIV', 'conf' => $conf,
                    'home' => null, 'away' => null, 'home_seed' => null, 'away_seed' => null,
                    'played' => false, 'home_score' => null, 'away_score' => null,
                    'winner' => null, 'log_id' => null,
                ];
            }
        }
        foreach (['AFC', 'NFC'] as $conf) {
            $games[] = [
                'id' => "{$conf}-CONF-1", 'round' => 'CONF', 'conf' => $conf,
                'home' => null, 'away' => null, 'home_seed' => null, 'away_seed' => null,
                'played' => false, 'home_score' => null, 'away_score' => null,
                'winner' => null, 'log_id' => null,
            ];
        }
        $games[] = [
            'id' => 'SB', 'round' => 'SB', 'conf' => 'NEUTRAL',
            'home' => null, 'away' => null, 'home_seed' => null, 'away_seed' => null,
            'played' => false, 'home_score' => null, 'away_score' => null,
            'winner' => null, 'log_id' => null,
        ];
        return ['seeds' => $seeds, 'games' => $games, 'champion' => null];
    }

    private static function seedOf(array $bracket, string $teamId): int {
        foreach (['AFC', 'NFC'] as $conf) {
            foreach ($bracket['seeds'][$conf] as $t) {
                if ($t['team'] === $teamId) return $t['seed'];
            }
        }
        return 99;
    }

    public static function advanceBracket(array $bracket, string $playedGameId, string $winnerId): array {
        $games = &$bracket['games'];
        $playedIdx = null;
        foreach ($games as $i => $g) if ($g['id'] === $playedGameId) { $playedIdx = $i; break; }
        if ($playedIdx === null) return $bracket;
        $played = &$games[$playedIdx];
        $played['winner'] = $winnerId;

        if ($played['round'] === 'WC') {
            $conf = $played['conf'];
            $wcGames = array_filter($games, static fn($g) => $g['round'] === 'WC' && $g['conf'] === $conf);
            $allPlayed = true;
            foreach ($wcGames as $g) if (empty($g['played'])) { $allPlayed = false; break; }
            if ($allPlayed) {
                $winners = [];
                foreach ($wcGames as $g) $winners[] = [$g['winner'], self::seedOf($bracket, $g['winner'])];
                usort($winners, static fn($a, $b) => $a[1] <=> $b[1]);
                $topSeed = null;
                foreach ($bracket['seeds'][$conf] as $t) if ($t['seed'] === 1) { $topSeed = $t; break; }
                foreach ($games as &$g) {
                    if ($g['id'] === "{$conf}-DIV-1") {
                        $g['home'] = $topSeed['team']; $g['home_seed'] = 1;
                        $g['away'] = $winners[2][0];  $g['away_seed'] = $winners[2][1];
                    } elseif ($g['id'] === "{$conf}-DIV-2") {
                        $g['home'] = $winners[0][0]; $g['home_seed'] = $winners[0][1];
                        $g['away'] = $winners[1][0]; $g['away_seed'] = $winners[1][1];
                    }
                }
                unset($g);
            }
        } elseif ($played['round'] === 'DIV') {
            $conf = $played['conf'];
            $divGames = array_filter($games, static fn($g) => $g['round'] === 'DIV' && $g['conf'] === $conf);
            $allPlayed = true;
            foreach ($divGames as $g) if (empty($g['played'])) { $allPlayed = false; break; }
            if ($allPlayed) {
                $winners = [];
                foreach ($divGames as $g) $winners[] = [$g['winner'], self::seedOf($bracket, $g['winner'])];
                usort($winners, static fn($a, $b) => $a[1] <=> $b[1]);
                foreach ($games as &$g) {
                    if ($g['id'] === "{$conf}-CONF-1") {
                        $g['home'] = $winners[0][0]; $g['home_seed'] = $winners[0][1];
                        $g['away'] = $winners[1][0]; $g['away_seed'] = $winners[1][1];
                    }
                }
                unset($g);
            }
        } elseif ($played['round'] === 'CONF') {
            $confGames = array_filter($games, static fn($g) => $g['round'] === 'CONF');
            $allPlayed = true;
            foreach ($confGames as $g) if (empty($g['played'])) { $allPlayed = false; break; }
            if ($allPlayed) {
                $afcWin = null; $nfcWin = null;
                foreach ($confGames as $g) {
                    if ($g['conf'] === 'AFC') $afcWin = $g['winner'];
                    else $nfcWin = $g['winner'];
                }
                foreach ($games as &$g) {
                    if ($g['id'] === 'SB') {
                        $g['home'] = $afcWin; $g['away'] = $nfcWin;
                        $g['home_seed'] = self::seedOf($bracket, $afcWin);
                        $g['away_seed'] = self::seedOf($bracket, $nfcWin);
                    }
                }
                unset($g);
            }
        } elseif ($played['round'] === 'SB') {
            $bracket['champion'] = $winnerId;
        }
        return $bracket;
    }
}
