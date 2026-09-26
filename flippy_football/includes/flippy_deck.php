<?php
/**
 * Flippy Deck — standalone PHP port of the NFL tabletop pre-play deck.
 * Composition (default balanced deck, 350 cards):
 *   - 250 DICE cards         "Roll the dice" (default flow)
 *   -  98 YARDS cards        override play yards, range -6..+45
 *   -   2 INJURY cards       concussion, 40% QB / 60% skill position
 *
 * Franchise custom decks:
 *   - preset:  balanced | power_run | air_raid | chaos
 *   - signature_cards: up to 3 manager-authored cards
 *       [ ['label' => 'Arrowhead Roar', 'yards' => 25, 'count' => 3], ... ]
 *     Signature cards REPLACE DICE cards to keep the deck at 350.
 */

final class FlippyDeck {
    public const DECK_SIZE           = 350;
    public const NUM_DICE            = 250;
    public const NUM_INJURY          = 2;
    public const MAX_SIGNATURE_CARDS = 3;
    public const MAX_SIGNATURE_COPIES = 3;

    /** @var array<int, array{0:int,1:int}> */
    private const YARDAGE_CURVE = [
        [-6, 1], [-5, 1], [-4, 2], [-3, 3], [-2, 4], [-1, 5],
        [0, 6],  [1, 8],  [2, 9],  [3, 10], [4, 9],  [5, 7],
        [6, 6],  [7, 5],  [8, 4],  [9, 3],  [10, 3],
        [11, 2], [12, 2], [13, 1], [14, 1], [15, 1],
        [18, 1], [22, 1], [28, 1], [45, 2],
    ]; // sums to 98

    private const POWER_RUN_CURVE = [
        [-4, 1], [-3, 2], [-2, 4], [-1, 6],
        [0, 8],  [1, 10], [2, 12], [3, 12], [4, 11], [5, 9],
        [6, 7],  [7, 5],  [8, 4],  [9, 3],  [10, 2],
        [12, 1], [15, 1],
    ]; // sums to 98

    private const AIR_RAID_CURVE = [
        [-8, 1], [-6, 2], [-4, 2], [-2, 2], [-1, 2],
        [0, 4],  [2, 3],  [4, 4],  [6, 5],  [8, 6],
        [10, 7], [12, 6], [15, 6], [18, 6], [22, 6],
        [28, 5], [35, 4], [45, 3], [55, 2],
        [1, 3],  [3, 3],  [5, 3],  [7, 3],  [9, 3],  [11, 3], [14, 2], [17, 2],
    ]; // sums to 98

    private const CHAOS_CURVE = [
        [-10, 3], [-8, 3], [-6, 3], [-4, 3], [-2, 3],
        [0, 4],   [2, 4],  [4, 4],  [6, 4],  [8, 4],
        [10, 5],  [15, 5], [20, 6], [25, 6], [30, 5],
        [35, 5],  [40, 5], [45, 5], [50, 5], [55, 4],
        [60, 4],  [70, 3], [80, 2],
    ]; // sums to 95, padded to 98 by _normalizeCurve

    /** @var array<string, array<array{0:int,1:int}>> */
    public static function presets(): array {
        return [
            'balanced'  => self::YARDAGE_CURVE,
            'power_run' => self::POWER_RUN_CURVE,
            'air_raid'  => self::normalizeCurve(self::AIR_RAID_CURVE),
            'chaos'     => self::normalizeCurve(self::CHAOS_CURVE),
        ];
    }

    /** @var array<int, array<string, mixed>> */
    private array $cards = [];
    public int $drawn = 0;
    public int $reshuffles = 0;
    private array $config;

    public function __construct(?array $config = null) {
        $this->config = $config ?? [];
        $this->cards  = self::buildDeckCards($this->config);
        shuffle($this->cards);
    }

    public function remaining(): int { return count($this->cards); }

    /** Draw the top card. Auto-reshuffles when empty using the same config. */
    public function draw(): array {
        if (empty($this->cards)) {
            $this->cards = self::buildDeckCards($this->config);
            shuffle($this->cards);
            $this->reshuffles++;
            $this->drawn = 0;
        }
        $card = array_pop($this->cards);
        $this->drawn++;
        return $card;
    }

    public static function buildDeckCards(?array $config = null): array {
        $config    = $config ?? [];
        $presetKey = strtolower((string)($config['preset'] ?? 'balanced'));
        $presets   = self::presets();
        $curve     = $presets[$presetKey] ?? $presets['balanced'];
        $signature = self::sanitizeSignatureCards($config['signature_cards'] ?? []);
        $sigTotal  = 0;
        foreach ($signature as $s) { $sigTotal += $s['count']; }

        // Signature cards REPLACE DICE cards to keep deck exactly at DECK_SIZE
        $diceCount = max(0, self::NUM_DICE - $sigTotal);

        $cards = [];
        for ($i = 0; $i < $diceCount; $i++) {
            $cards[] = ['type' => 'DICE'];
        }
        foreach ($curve as [$yards, $count]) {
            for ($i = 0; $i < $count; $i++) {
                $cards[] = ['type' => 'YARDS', 'yards' => $yards];
            }
        }
        for ($i = 0; $i < self::NUM_INJURY; $i++) {
            $cards[] = ['type' => 'INJURY'];
        }
        foreach ($signature as $sig) {
            for ($i = 0; $i < $sig['count']; $i++) {
                $cards[] = [
                    'type'      => 'YARDS',
                    'yards'     => $sig['yards'],
                    'signature' => true,
                    'label'     => $sig['label'],
                ];
            }
        }
        // Force exact size — pad with DICE if short, trim DICE if long
        while (count($cards) < self::DECK_SIZE) {
            $cards[] = ['type' => 'DICE'];
        }
        while (count($cards) > self::DECK_SIZE) {
            for ($i = count($cards) - 1; $i >= 0; $i--) {
                if ($cards[$i]['type'] === 'DICE') {
                    array_splice($cards, $i, 1);
                    break;
                }
            }
        }
        return $cards;
    }

    /**
     * 40% QB / 60% RB/WR/TE/K. Falls back to any healthy starter.
     * $offPlayers is a list of ['name' => .., 'pos' => .., 'starter' => bool, 'injured' => bool, 'ovr' => int, ...]
     * Returns the chosen victim (or null) — does NOT mutate; call applyConcussion for that.
     */
    public static function pickInjuryTarget(array $offPlayers): ?array {
        $healthyStarters = array_values(array_filter($offPlayers, static fn($p) =>
            !empty($p['starter']) && empty($p['injured'])
        ));
        if (empty($healthyStarters)) return null;

        $preferQb = (mt_rand() / mt_getrandmax()) < 0.40;
        $pool = [];
        if ($preferQb) {
            $pool = array_values(array_filter($healthyStarters, static fn($p) => ($p['pos'] ?? '') === 'QB'));
        }
        if (empty($pool)) {
            $pool = array_values(array_filter($healthyStarters, static fn($p) =>
                in_array(($p['pos'] ?? ''), ['RB','WR','TE','K'], true)
            ));
        }
        if (empty($pool)) $pool = $healthyStarters;
        return $pool[array_rand($pool)];
    }

    /**
     * Mark victim injured/out & promote first healthy backup at same position.
     * Mutates $offPlayers by reference. Returns an injury descriptor.
     */
    public static function applyConcussion(array &$offPlayers, array $victim): array {
        $pos = $victim['pos'] ?? null;
        foreach ($offPlayers as &$p) {
            if (($p['name'] ?? null) === ($victim['name'] ?? null)) {
                $p['starter']     = false;
                $p['injured']     = true;
                $p['injury_desc'] = 'Concussion (out for game)';
            }
        }
        unset($p);

        // Promote the highest-OVR healthy player at that position if no starter exists there now
        $healthyAtPos = array_filter($offPlayers, static fn($p) =>
            ($p['pos'] ?? null) === $pos && empty($p['injured'])
        );
        $hasStarter = false;
        foreach ($healthyAtPos as $p) { if (!empty($p['starter'])) { $hasStarter = true; break; } }
        $replacement = null;
        if (!$hasStarter && !empty($healthyAtPos)) {
            usort($healthyAtPos, static fn($a, $b) => ($b['ovr'] ?? 0) <=> ($a['ovr'] ?? 0));
            $top = $healthyAtPos[0];
            foreach ($offPlayers as &$p) {
                if (($p['name'] ?? null) === $top['name']) {
                    $p['starter'] = true;
                    $replacement  = $top['name'];
                    break;
                }
            }
            unset($p);
        }
        return [
            'player'      => $victim['name']  ?? null,
            'pos'         => $pos,
            'team'        => $victim['team']  ?? null,
            'desc'        => 'Concussion — OUT for game',
            'replacement' => $replacement,
        ];
    }

    // ---------- Static procedural wrappers (used by SimEngine) ----------
    public static function newDeck(?array $config = null): array {
        $cards = self::buildDeckCards($config);
        shuffle($cards);
        return [
            'cards'      => $cards,
            'drawn'      => 0,
            'reshuffles' => 0,
            'config'     => $config ?? [],
        ];
    }

    public static function drawFrom(array &$deck): array {
        if (empty($deck['cards'])) {
            $deck['cards'] = self::buildDeckCards($deck['config'] ?? null);
            shuffle($deck['cards']);
            $deck['reshuffles']++;
            $deck['drawn'] = 0;
        }
        $card = array_pop($deck['cards']);
        $deck['drawn']++;
        return $card;
    }

    public static function remainingIn(array $deck): int {
        return count($deck['cards']);
    }

    // ---------------- helpers ----------------
    private static function sanitizeSignatureCards(?array $cards): array {
        if (empty($cards)) return [];
        $out = [];
        foreach (array_slice($cards, 0, self::MAX_SIGNATURE_CARDS) as $c) {
            if (!is_array($c)) continue;
            $yards = (int)($c['yards'] ?? 0);
            $yards = max(-10, min(60, $yards));
            $count = (int)($c['count'] ?? 1);
            $count = max(1, min(self::MAX_SIGNATURE_COPIES, $count));
            $label = (string)($c['label'] ?? "Signature " . ($yards >= 0 ? "+$yards" : $yards));
            if (strlen($label) > 24) $label = substr($label, 0, 24);
            $out[] = ['label' => $label, 'yards' => $yards, 'count' => $count];
        }
        return $out;
    }

    /** Force a curve to sum to the target (default 98) — trims heaviest or pads midpoint. */
    private static function normalizeCurve(array $curve, int $target = 98): array {
        $total = 0;
        foreach ($curve as [$_, $c]) { $total += $c; }
        if ($total === $target) return $curve;
        $delta = $target - $total;
        if ($delta > 0) {
            $mid = $curve[intdiv(count($curve), 2)];
            $curve[] = [$mid[0], $delta];
        } else {
            $iMax = 0;
            foreach ($curve as $i => [$_, $c]) {
                if ($c > $curve[$iMax][1]) $iMax = $i;
            }
            $curve[$iMax][1] = max(1, $curve[$iMax][1] + $delta);
        }
        return $curve;
    }
}
