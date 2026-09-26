<?php
final class Weather {
    public const TYPES = [
        'CLEAR' => ['label' => 'Clear',  'pass_mult' => 1.0,  'int_bonus' => 0,  'big_play_mult' => 1.0,  'fg_penalty' => 0,   'kick_penalty' => 0,  'icon' => 'sun',        'color' => '#F59E0B'],
        'RAIN'  => ['label' => 'Rain',   'pass_mult' => 0.82, 'int_bonus' => 1,  'big_play_mult' => 0.85, 'fg_penalty' => 4,   'kick_penalty' => 3,  'icon' => 'cloud-rain', 'color' => '#38BDF8'],
        'SNOW'  => ['label' => 'Snow',   'pass_mult' => 0.7,  'int_bonus' => 1,  'big_play_mult' => 0.65, 'fg_penalty' => 8,   'kick_penalty' => 6,  'icon' => 'cloud-snow', 'color' => '#E2E8F0'],
        'WIND'  => ['label' => 'Windy',  'pass_mult' => 0.9,  'int_bonus' => 0,  'big_play_mult' => 0.5,  'fg_penalty' => 10,  'kick_penalty' => 6,  'icon' => 'wind',       'color' => '#A5F3FC'],
        'DOME'  => ['label' => 'Dome',   'pass_mult' => 1.05, 'int_bonus' => -1, 'big_play_mult' => 1.1,  'fg_penalty' => -3,  'kick_penalty' => -2, 'icon' => 'home',       'color' => '#C084FC'],
    ];

    public const DOMED_TEAMS = ['MIN', 'DET', 'NO', 'ATL', 'LV', 'IND', 'LAR', 'ARI', 'HOU', 'DAL'];

    public static function roll(string $homeId): string {
        if (in_array($homeId, self::DOMED_TEAMS, true) && (mt_rand() / mt_getrandmax()) < 0.9) {
            return 'DOME';
        }
        $r = mt_rand() / mt_getrandmax();
        if ($r < 0.62) return 'CLEAR';
        if ($r < 0.78) return 'WIND';
        if ($r < 0.92) return 'RAIN';
        return 'SNOW';
    }

    public static function info(string $code): array {
        return ['code' => $code] + (self::TYPES[$code] ?? self::TYPES['CLEAR']);
    }
}
