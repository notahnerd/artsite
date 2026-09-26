<?php
final class Injuries {
    public const CHANCE = 0.045;
    public const AT_RISK = ['QB', 'RB', 'WR', 'TE', 'K'];
    public const DESCRIPTIONS = [
        'hamstring strain', 'ankle sprain', 'shoulder tweak', 'knee soreness',
        'concussion protocol', 'hip pointer', 'ribs contusion', 'wrist injury',
        'high ankle sprain', 'back tightness',
    ];
    public const WEEKS = [1, 2, 3, 4, 5, 8];
    public const WEIGHTS = [35, 30, 15, 10, 6, 4];

    /** Returns list of new injuries for starters that played this game. */
    public static function rollForGame(array $offPlayers): array {
        $out = [];
        foreach ($offPlayers as $p) {
            if (empty($p['starter'])) continue;
            if (!in_array($p['pos'] ?? '', self::AT_RISK, true)) continue;
            if ((mt_rand() / mt_getrandmax()) < self::CHANCE) {
                $out[] = [
                    'player' => $p['name'],
                    'pos' => $p['pos'],
                    'weeks_out' => self::weightedPick(self::WEEKS, self::WEIGHTS),
                    'desc' => self::DESCRIPTIONS[array_rand(self::DESCRIPTIONS)],
                ];
            }
        }
        return $out;
    }

    public static function active(array $list, int $currentWeek): array {
        return array_values(array_filter($list, static fn($i) =>
            ($i['injured_week'] + $i['weeks_out']) > $currentWeek
        ));
    }

    public static function injuredNames(array $list, int $currentWeek): array {
        return array_map(static fn($i) => $i['player'], self::active($list, $currentWeek));
    }

    private static function weightedPick(array $values, array $weights): int {
        $total = array_sum($weights);
        $r = mt_rand(1, $total);
        $cum = 0;
        foreach ($values as $i => $v) {
            $cum += $weights[$i];
            if ($r <= $cum) return $v;
        }
        return $values[0];
    }
}
