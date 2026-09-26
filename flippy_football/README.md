# Flippy Football — Flippy Deck (PHP prototype)

A standalone PHP port of the **Flippy Deck** — the pre-play mechanic from the NFL tabletop sim. This is a **core-logic prototype**, not a full port of the app. Perfect for wiring into any PHP framework (Laravel, Symfony, plain PHP) or running as a CLI demo.

## What's here

| File | Purpose |
| --- | --- |
| `flippy_deck.php` | The `FlippyDeck` class — 350-card deck, 4 skew presets, signature cards, concussion injuries |
| `demo.php`        | CLI runner that shuffles a deck, draws N cards, prints a summary + an injury walk-through |
| `README.md`       | You're reading it |

## Requirements

- **PHP 8.1+** (uses typed properties, match expressions, first-class callable syntax)
- No Composer, no extensions required (removed `mbstring` dep on purpose)

## Quick start

```bash
# 1) Copy this folder to your machine (if you haven't already)
git clone <your-emergent-repo> "flippy football"
cd "flippy football"

# 2) Run the demo
php demo.php                                  # balanced deck, 350 draws
php demo.php --preset=chaos --draws=500       # chaos + a reshuffle
php demo.php --preset=air_raid --sig          # add 6 signature cards
```

## Deck composition (default = balanced, 350 cards)

| Card type | Count | Behavior |
| --- | ---: | --- |
| **DICE**   | 250 | "Roll the dice" — falls through to your dice-chart resolution |
| **YARDS**  |  98 | Overrides play yards. Default range: **-6 .. +45**. Realistic right-skewed curve |
| **INJURY** |   2 | Random offensive starter concussed (out for game). **40% QB / 60% skill** |

## Yardage-skew presets

| Preset | Feel | Yards range |
| --- | --- | --- |
| `balanced`  | Realistic curve, rare breakaways | -6 .. +45 |
| `power_run` | Grind-it-out, more short gains   | -4 .. +15 |
| `air_raid`  | Boom-or-bust downfield attack     | -8 .. +55 |
| `chaos`     | Wild swings both directions       | -10 .. +80 |

## Signature cards

Manager-authored cards (up to 3, 1–3 copies each). They **replace DICE cards** so the deck stays at 350.

```php
$config = [
    'preset' => 'chaos',
    'signature_cards' => [
        ['label' => 'Arrowhead Roar', 'yards' => 25, 'count' => 3],
        ['label' => 'Fumbleroski',    'yards' => -8, 'count' => 2],
        ['label' => 'Philly Special', 'yards' => 45, 'count' => 1],
    ],
];
$deck = new FlippyDeck($config);
```

Constraints (auto-enforced by `sanitizeSignatureCards`):
- Max **3** signature cards
- Yards clamp to **-10 .. +60**
- Copies clamp to **1 .. 3**
- Label truncates at **24** characters

## API

```php
$deck  = new FlippyDeck($config);   // config is optional; defaults to balanced
$card  = $deck->draw();             // pop next card; auto-reshuffles when empty
$left  = $deck->remaining();        // int, cards left in the current shuffle
$deck->drawn;                       // total draws since last reshuffle
$deck->reshuffles;                  // reshuffle count for this deck instance
```

### Card shapes

```php
['type' => 'DICE']
['type' => 'YARDS',  'yards' => 8]
['type' => 'YARDS',  'yards' => 25, 'signature' => true, 'label' => 'Arrowhead Roar']
['type' => 'INJURY']  // apply the injury yourself with the helpers below
```

### Injury helpers

```php
$victim = FlippyDeck::pickInjuryTarget($offensePlayers);
if ($victim) {
    $inj = FlippyDeck::applyConcussion($offensePlayers, $victim);
    // $inj = ['player' => ..., 'pos' => ..., 'desc' => ..., 'replacement' => ...]
}
```

`$offensePlayers` is a plain array of dicts. Each player must have `name`, `pos`, `starter` (bool), `injured` (bool), and ideally `ovr` (int, drives who gets promoted).

## How to wire into your PHP app

Any drive loop that resolves a RUN/PASS play:

```php
foreach ($drive->plays() as $play) {
    if (!in_array($play->type, ['RUN', 'PASS'], true)) continue;

    $card = $deck->draw();

    if ($card['type'] === 'INJURY') {
        $victim = FlippyDeck::pickInjuryTarget($offense);
        if ($victim) FlippyDeck::applyConcussion($offense, $victim);
    } elseif ($card['type'] === 'YARDS') {
        $play->yards        = $card['yards'];    // override the dice chart
        $play->cardOverride = true;              // skip fumble/INT rolls
    }
    // else: type === 'DICE' → fall through to your existing chart lookup
}
```

## Not included in this prototype

The full Python app has these pieces you'd need to build yourself if porting further:

- Play-by-play sim engine (3d6 + 1d6 chart resolution)
- Player cards (3-18 accuracy charts)
- Weather, coaching philosophies, playbooks, rivalries
- MongoDB persistence, ownership tokens, rate limits
- React frontend with the flip-card animation

Ping me if you want any of those ported next.
