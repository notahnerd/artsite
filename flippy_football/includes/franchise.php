<?php
require_once __DIR__ . '/nfl_data.php';

final class Franchise {
    public const RETIREMENT_AGE_START = 33;
    public const ROOKIE_FIRST = ['Tyler','Jayden','Marcus','Trey','DeAndre','Malik','Kyle','Isaiah','Xavier','Devin','Jalen','Cody','Trevon','Trevor','Micah','Chase','Justin','Jordan','Cam','Andre','Blake','Ethan','Damon','Brock'];
    public const ROOKIE_LAST  = ['Reed','Harris','Miller','Thompson','Jackson','Martin','Hayes','Bennett','Foster','Hughes','Grant','Brooks','Owens','Rivers','Sanders','Coleman','Hall','Watkins','Morris','Simmons'];
    public const POSITIONS_ROOKIES = ['QB','RB','WR','WR','TE','K'];

    private static function ageHash(string $name): int {
        return (int)hexdec(substr(md5($name), 0, 6)) % 15;
    }

    public static function ageAndRetire(array $roster): array {
        $retired = []; $survivors = [];
        foreach ($roster as $p) {
            $age = ($p['age'] ?? (24 + self::ageHash($p['name']))) + 1;
            $chance = 0.0;
            if ($age >= 33) $chance += 0.15;
            if ($age >= 35) $chance += 0.25;
            if ($age >= 37) $chance += 0.4;
            if (($p['ovr'] ?? 75) < 72 && $age >= 32) $chance += 0.25;
            if ((mt_rand() / mt_getrandmax()) < $chance) {
                $retired[] = $p + ['age' => $age];
                continue;
            }
            $ovr = $p['ovr'] ?? 75;
            if ($age <= 25)      $ovr = min(99, $ovr + [0,1,1,2][array_rand([0,1,1,2])]);
            elseif ($age <= 29)  $ovr = min(99, $ovr + [-1,0,0,1][array_rand([-1,0,0,1])]);
            elseif ($age <= 32)  $ovr = max(60, $ovr + [-2,-1,0][array_rand([-2,-1,0])]);
            else                 $ovr = max(60, $ovr + [-3,-2,-1][array_rand([-3,-2,-1])]);
            $survivors[] = $p + ['age' => $age, 'ovr' => $ovr];
        }
        return ['roster' => $survivors, 'retired' => $retired];
    }

    public static function generateRookies(string $teamId, int $count = 3): array {
        $seed = array_sum(array_map('ord', str_split($teamId))) + mt_rand(0, 999);
        $positions = self::POSITIONS_ROOKIES;
        shuffle($positions);
        $picks = array_slice($positions, 0, min($count, count($positions)));
        $out = [];
        foreach ($picks as $i => $pos) {
            $fn = self::ROOKIE_FIRST[($seed + $i * 3) % count(self::ROOKIE_FIRST)];
            $ln = self::ROOKIE_LAST[($seed + $i * 5 + 7) % count(self::ROOKIE_LAST)];
            $base = 68 + mt_rand(0, 22);
            $out[] = [
                'name' => "$fn $ln", 'pos' => $pos, 'ovr' => $base,
                'num'  => 10 + ($seed + $i) % 80,
                'age'  => 22, 'rookie' => true,
            ];
        }
        return $out;
    }

    public static function rollFranchiseYear(array $seasonDoc): array {
        $changes = [];
        foreach (NflData::TEAMS as $team) {
            $tid = $team['id'];
            $base = $seasonDoc['franchise_rosters'][$tid] ?? (NflData::PLAYERS[$tid] ?? []);
            $aged = self::ageAndRetire($base);
            $count = [2, 3, 3, 4][array_rand([2, 3, 3, 4])];
            $rookies = self::generateRookies($tid, $count);
            $changes[$tid] = [
                'roster'   => array_merge($aged['roster'], $rookies),
                'retired'  => $aged['retired'],
                'rookies'  => $rookies,
            ];
        }
        return $changes;
    }
}
