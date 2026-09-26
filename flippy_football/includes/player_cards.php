<?php
/**
 * Strat-O-Matic style player + team defense cards (3D6 chart).
 * Ported from player_cards.py.
 */
final class PlayerCards {
    private static function seed(string $name): int {
        return (int)hexdec(substr(md5($name), 0, 8));
    }

    /** 60 -> 0.7, 80 -> 0.95, 99 -> 1.2 */
    private static function skew(int $ovr): float {
        $o = max(60, min(99, $ovr));
        return 0.7 + ($o - 60) * (0.5 / 39);
    }

    public static function qbCard(array $player): array {
        $ovr = (int)$player['ovr'];
        $s = self::skew($ovr);
        $style = self::seed($player['name']) % 3;
        $base = [
            3  => ['yards' => 0,   'event' => 'INT'],
            4  => ['yards' => 0,   'event' => 'INT'],
            5  => ['yards' => -8,  'event' => 'SACK'],
            6  => ['yards' => 0,   'event' => 'INCOMPLETE'],
            7  => ['yards' => 0,   'event' => 'INCOMPLETE'],
            8  => ['yards' => 3,   'event' => 'NORMAL'],
            9  => ['yards' => 5,   'event' => 'NORMAL'],
            10 => ['yards' => 7,   'event' => 'NORMAL'],
            11 => ['yards' => 9,   'event' => 'NORMAL'],
            12 => ['yards' => 12,  'event' => 'NORMAL'],
            13 => ['yards' => 15,  'event' => 'NORMAL'],
            14 => ['yards' => 20,  'event' => 'NORMAL'],
            15 => ['yards' => 26,  'event' => 'BIG_PLAY'],
            16 => ['yards' => 34,  'event' => 'BIG_PLAY'],
            17 => ['yards' => 48,  'event' => 'DEEP_BOMB'],
            18 => ['yards' => 65,  'event' => 'DEEP_BOMB'],
        ];
        foreach ($base as $r => &$cell) {
            $y = $cell['yards'];
            if ($y > 0)  $cell['yards'] = max(1, (int)round($y * $s));
            elseif ($y < 0 && $r === 5) $cell['yards'] = (int)round($y / $s);
        }
        unset($cell);
        if ($style === 0) {          // gunslinger
            foreach ([15, 16, 17, 18] as $r) $base[$r]['yards'] = (int)($base[$r]['yards'] * 1.15);
        } elseif ($style === 2) {    // game-manager
            $base[6] = ['yards' => 2, 'event' => 'NORMAL'];
            $base[7] = ['yards' => 4, 'event' => 'NORMAL'];
            foreach ([17, 18] as $r) $base[$r]['yards'] = (int)($base[$r]['yards'] * 0.8);
        }
        return $base;
    }

    public static function rbCard(array $player): array {
        $ovr = (int)$player['ovr'];
        $s = self::skew($ovr);
        $style = self::seed($player['name']) % 3;
        $base = [
            3 => ['yards'=>-5,'event'=>'TFL'], 4 => ['yards'=>-3,'event'=>'TFL'],
            5 => ['yards'=>-1,'event'=>'NORMAL'], 6 => ['yards'=>0,'event'=>'NORMAL'],
            7 => ['yards'=>1,'event'=>'NORMAL'], 8 => ['yards'=>2,'event'=>'NORMAL'],
            9 => ['yards'=>3,'event'=>'NORMAL'], 10 => ['yards'=>4,'event'=>'NORMAL'],
            11 => ['yards'=>5,'event'=>'NORMAL'], 12 => ['yards'=>7,'event'=>'NORMAL'],
            13 => ['yards'=>9,'event'=>'NORMAL'], 14 => ['yards'=>12,'event'=>'NORMAL'],
            15 => ['yards'=>16,'event'=>'BIG_PLAY'], 16 => ['yards'=>22,'event'=>'BIG_PLAY'],
            17 => ['yards'=>32,'event'=>'BREAKAWAY'], 18 => ['yards'=>55,'event'=>'BREAKAWAY'],
        ];
        foreach ($base as &$cell) {
            if ($cell['yards'] > 0) $cell['yards'] = max(1, (int)round($cell['yards'] * $s));
        }
        unset($cell);
        if ($style === 0) {   // power
            foreach ([5, 6, 7, 8] as $r) $base[$r]['yards'] += 1;
            $base[17]['yards'] = (int)($base[17]['yards'] * 0.8);
            $base[18]['yards'] = (int)($base[18]['yards'] * 0.8);
        }
        if ($style === 2) {   // speed
            $base[6]['yards']  -= 1;
            $base[15]['yards'] = (int)($base[15]['yards'] * 1.2);
            $base[17]['yards'] = (int)($base[17]['yards'] * 1.25);
            $base[18]['yards'] = (int)($base[18]['yards'] * 1.3);
        }
        return $base;
    }

    public static function wrCard(array $player): array {
        $ovr = (int)$player['ovr'];
        $s = self::skew($ovr);
        $out = [];
        for ($r = 3; $r <= 18; $r++) {
            $out[$r] = [
                'yac'  => max(0, (int)round(($r - 8) * $s * 0.6)),
                'drop' => $r === 6 && $ovr < 78,
            ];
        }
        return $out;
    }

    public static function teamPassDefenseCard(array $team): array {
        $d = (int)$team['def'];
        $s = self::skew($d);
        $base = [
            3 => ['yards'=>22,'event'=>'COVERAGE_BUST'], 4 => ['yards'=>15,'event'=>'COVERAGE_BUST'],
            5 => ['yards'=>11,'event'=>'NORMAL'], 6 => ['yards'=>8,'event'=>'NORMAL'],
            7 => ['yards'=>6,'event'=>'NORMAL'], 8 => ['yards'=>5,'event'=>'NORMAL'],
            9 => ['yards'=>4,'event'=>'NORMAL'], 10 => ['yards'=>3,'event'=>'NORMAL'],
            11 => ['yards'=>0,'event'=>'INCOMPLETE'], 12 => ['yards'=>0,'event'=>'INCOMPLETE'],
            13 => ['yards'=>0,'event'=>'INCOMPLETE'], 14 => ['yards'=>-3,'event'=>'HURRY'],
            15 => ['yards'=>-7,'event'=>'SACK'], 16 => ['yards'=>-9,'event'=>'SACK'],
            17 => ['yards'=>0,'event'=>'INT'], 18 => ['yards'=>-12,'event'=>'INT_RETURN_TD'],
        ];
        foreach ([14, 15, 16, 17, 18] as $r) $base[$r]['yards'] = (int)round($base[$r]['yards'] * $s);
        foreach ([3, 4] as $r) $base[$r]['yards'] = (int)round($base[$r]['yards'] * (2.0 - $s));
        return $base;
    }

    public static function teamRunDefenseCard(array $team): array {
        $d = (int)$team['def'];
        $s = self::skew($d);
        $base = [
            3 => ['yards'=>30,'event'=>'BREAKAWAY'], 4 => ['yards'=>18,'event'=>'BIG_PLAY'],
            5 => ['yards'=>10,'event'=>'NORMAL'], 6 => ['yards'=>7,'event'=>'NORMAL'],
            7 => ['yards'=>5,'event'=>'NORMAL'], 8 => ['yards'=>4,'event'=>'NORMAL'],
            9 => ['yards'=>3,'event'=>'NORMAL'], 10 => ['yards'=>2,'event'=>'NORMAL'],
            11 => ['yards'=>1,'event'=>'NORMAL'], 12 => ['yards'=>0,'event'=>'NORMAL'],
            13 => ['yards'=>-1,'event'=>'NORMAL'], 14 => ['yards'=>-3,'event'=>'TFL'],
            15 => ['yards'=>-4,'event'=>'STUFF'], 16 => ['yards'=>-2,'event'=>'STUFF'],
            17 => ['yards'=>-1,'event'=>'FUMBLE'], 18 => ['yards'=>0,'event'=>'FUMBLE'],
        ];
        foreach ([12, 13, 14, 15, 16, 17, 18] as $r) $base[$r]['yards'] = (int)round($base[$r]['yards'] * $s);
        foreach ([3, 4] as $r) $base[$r]['yards'] = (int)round($base[$r]['yards'] * (2.0 - $s));
        return $base;
    }

    /** Returns [yards, event] */
    public static function resolvePass(array $qb, array $wr, array $defTeam, int $rollSum, string $read): array {
        if ($read === 'DEF') {
            $cell = self::teamPassDefenseCard($defTeam)[$rollSum];
            return [$cell['yards'], $cell['event']];
        }
        $qbc = self::qbCard($qb);
        $wrc = self::wrCard($wr);
        $cell = $qbc[$rollSum];
        $yards = $cell['yards'];
        $event = $cell['event'];
        if (in_array($event, ['NORMAL', 'BIG_PLAY', 'DEEP_BOMB'], true) && $yards > 0) {
            $yards += $wrc[$rollSum]['yac'];
        }
        if ($event === 'NORMAL' && !empty($wrc[$rollSum]['drop'])) return [0, 'DROP'];
        return [$yards, $event];
    }

    public static function resolveRun(array $rb, array $defTeam, int $rollSum, string $read): array {
        if ($read === 'DEF') {
            $cell = self::teamRunDefenseCard($defTeam)[$rollSum];
            return [$cell['yards'], $cell['event']];
        }
        $cell = self::rbCard($rb)[$rollSum];
        return [$cell['yards'], $cell['event']];
    }

    /** Kicker card. Returns [chart, maxRange]. */
    public static function kCard(array $player): array {
        $ovr = (int)$player['ovr'];
        $s = self::skew($ovr);
        $maxRange = 40 + (int)round(($ovr - 60) / 3);
        $chart = [
            3  => ['result' => 'WIDE_LEFT',  'range_bonus' => 0,  'make' => false],
            4  => ['result' => 'WIDE_RIGHT', 'range_bonus' => 0,  'make' => false],
            5  => ['result' => 'HOOK',       'range_bonus' => -8, 'make' => null],
            6  => ['result' => 'SLICE',      'range_bonus' => -4, 'make' => null],
            7  => ['result' => 'STRAIGHT',   'range_bonus' => 0,  'make' => null],
            8  => ['result' => 'STRAIGHT',   'range_bonus' => 1,  'make' => null],
            9  => ['result' => 'STRAIGHT',   'range_bonus' => 3,  'make' => null],
            10 => ['result' => 'STRAIGHT',   'range_bonus' => 5,  'make' => null],
            11 => ['result' => 'STRAIGHT',   'range_bonus' => 6,  'make' => null],
            12 => ['result' => 'STRAIGHT',   'range_bonus' => 7,  'make' => null],
            13 => ['result' => 'STRAIGHT',   'range_bonus' => 8,  'make' => null],
            14 => ['result' => 'PURE',       'range_bonus' => 10, 'make' => null],
            15 => ['result' => 'PURE',       'range_bonus' => 12, 'make' => null],
            16 => ['result' => 'BOMB',       'range_bonus' => 15, 'make' => null],
            17 => ['result' => 'BOMB',       'range_bonus' => 18, 'make' => null],
            18 => ['result' => 'BOOMSTICK',  'range_bonus' => 25, 'make' => true],
        ];
        return [$chart, $maxRange];
    }
}
