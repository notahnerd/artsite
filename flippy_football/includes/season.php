<?php
require_once __DIR__ . '/nfl_data.php';

final class Season {
    /** Round-robin-style random schedule generator. */
    public static function generateSchedule(string $seasonId, int $weeks = 18): array {
        $teams = array_column(NflData::TEAMS, 'id');
        shuffle($teams);
        $schedule = [];
        $pairCounts = [];

        for ($week = 1; $week <= $weeks; $week++) {
            shuffle($teams);
            $used = [];
            $matchups = [];
            for ($i = 0; $i < count($teams); $i++) {
                if (in_array($teams[$i], $used, true)) continue;
                for ($j = $i + 1; $j < count($teams); $j++) {
                    if (in_array($teams[$j], $used, true)) continue;
                    $pair = self::pair($teams[$i], $teams[$j]);
                    if (($pairCounts[$pair] ?? 0) >= 2) continue;
                    if ((mt_rand() / mt_getrandmax()) < 0.5) {
                        [$home, $away] = [$teams[$i], $teams[$j]];
                    } else {
                        [$home, $away] = [$teams[$j], $teams[$i]];
                    }
                    $matchups[] = [
                        'week'       => $week,
                        'home'       => $home,
                        'away'       => $away,
                        'played'     => false,
                        'home_score' => null,
                        'away_score' => null,
                        'game_id'    => "$seasonId-W$week-$home-$away",
                    ];
                    $used[] = $teams[$i];
                    $used[] = $teams[$j];
                    $pairCounts[$pair] = ($pairCounts[$pair] ?? 0) + 1;
                    break;
                }
            }
            $schedule = array_merge($schedule, $matchups);
        }
        return $schedule;
    }

    public static function computeStandings(array $schedule): array {
        $stats = [];
        foreach (NflData::TEAMS as $t) {
            $stats[$t['id']] = [
                'team' => $t['id'], 'w' => 0, 'l' => 0, 't' => 0, 'pf' => 0, 'pa' => 0,
                'div' => $t['div'], 'conf' => $t['conf'],
            ];
        }
        foreach ($schedule as $g) {
            if (empty($g['played'])) continue;
            [$h, $a] = [$g['home'], $g['away']];
            $hs = $g['home_score']; $as = $g['away_score'];
            $stats[$h]['pf'] += $hs; $stats[$h]['pa'] += $as;
            $stats[$a]['pf'] += $as; $stats[$a]['pa'] += $hs;
            if ($hs > $as)      { $stats[$h]['w']++; $stats[$a]['l']++; }
            elseif ($hs < $as)  { $stats[$a]['w']++; $stats[$h]['l']++; }
            else                { $stats[$h]['t']++; $stats[$a]['t']++; }
        }
        $out = array_values($stats);
        foreach ($out as &$s) {
            $games = $s['w'] + $s['l'] + $s['t'];
            $s['pct']  = $games ? round(($s['w'] + $s['t'] * 0.5) / $games, 3) : 0.0;
            $s['diff'] = $s['pf'] - $s['pa'];
        }
        unset($s);
        usort($out, static fn($a, $b) => [-$a['pct'], -$a['diff'], -$a['pf']] <=> [-$b['pct'], -$b['diff'], -$b['pf']]);
        return $out;
    }

    private static function pair(string $a, string $b): string {
        $arr = [$a, $b];
        sort($arr);
        return $arr[0] . '|' . $arr[1];
    }
}
