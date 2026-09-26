<?php
require_once __DIR__ . '/nfl_data.php';

final class Rivalries {
    /** Classic historical rivalries beyond division. */
    public const LEGACY = [
        ['KC', 'LV'], ['KC', 'DEN'],
        ['BAL', 'PIT'],
        ['DAL', 'SF'], ['DAL', 'PHI'],
        ['GB', 'CHI'],
        ['NE', 'NYJ'],
        ['MIA', 'BUF'],
        ['PHI', 'NYG'],
        ['SEA', 'SF'],
        ['LAR', 'SF'],
    ];

    public static function divisionRivals(string $teamId): array {
        $t = NflData::getTeam($teamId);
        if (!$t) return [];
        $out = [];
        foreach (NflData::TEAMS as $x) {
            if ($x['conf'] === $t['conf'] && $x['div'] === $t['div'] && $x['id'] !== $teamId) {
                $out[] = $x['id'];
            }
        }
        return $out;
    }

    public static function isRivalry(string $a, string $b, array $extraRivalFor = []): bool {
        if (in_array($b, self::divisionRivals($a), true)) return true;
        $pair = self::pair($a, $b);
        foreach (self::LEGACY as $l) {
            if (self::pair($l[0], $l[1]) === $pair) return true;
        }
        if (($extraRivalFor[$a] ?? null) === $b) return true;
        if (($extraRivalFor[$b] ?? null) === $a) return true;
        return false;
    }

    public static function annotateSchedule(array &$schedule, array $extraRivalFor = []): void {
        foreach ($schedule as &$g) {
            $g['rivalry'] = self::isRivalry($g['home'], $g['away'], $extraRivalFor);
        }
        unset($g);
    }

    private static function pair(string $a, string $b): string {
        $arr = [$a, $b];
        sort($arr);
        return $arr[0] . '|' . $arr[1];
    }
}
