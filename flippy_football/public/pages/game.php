<?php
/** @var array $pageParams */
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/helpers.php';

$stmt = db()->prepare('SELECT * FROM seasons WHERE id = ?');
$stmt->execute([$pageParams['season_id']]);
$row = $stmt->fetch();
if (!$row) { echo '<div class="card"><h1>Season not found</h1></div>'; return; }
$s = decode_season($row);
$game = null;
foreach ($s['schedule'] as $g) if ($g['game_id'] === $pageParams['game_id']) { $game = $g; break; }
if (!$game) { echo '<div class="card"><h1>Game not found</h1></div>'; return; }
?>
<section class="card game-shell" id="game-shell"
         data-season="<?= htmlspecialchars($s['id']) ?>"
         data-game="<?= htmlspecialchars($game['game_id']) ?>"
         data-home="<?= htmlspecialchars($game['home']) ?>"
         data-away="<?= htmlspecialchars($game['away']) ?>"
         data-played="<?= $game['played'] ? '1' : '0' ?>"
         data-logid="<?= htmlspecialchars($game['log_id'] ?? '') ?>">
    <div class="scoreboard">
        <div class="score-side away">
            <div class="team-abbr"><?= htmlspecialchars($game['away']) ?></div>
            <div class="score" id="score-away">0</div>
        </div>
        <div class="score-mid">
            <div class="qtime">Q<span id="q">1</span> · <span id="clock">15:00</span></div>
            <div class="down"><span id="down">1st</span> & <span id="dist">10</span></div>
            <div class="hash">Ball on <span id="ballon">25</span></div>
        </div>
        <div class="score-side home">
            <div class="team-abbr"><?= htmlspecialchars($game['home']) ?></div>
            <div class="score" id="score-home">0</div>
        </div>
    </div>
</section>

<section class="row-2col mt-4">
    <div class="card">
        <div class="dice-and-deck">
            <!-- Dice tray -->
            <div class="dice-tray" data-testid="dice-tray">
                <div class="eyebrow">3D6 + RED DIE</div>
                <div class="dice-row">
                    <div class="die white" id="d1">1</div>
                    <div class="die white" id="d2">1</div>
                    <div class="die white" id="d3">1</div>
                    <div class="die red" id="dr">1</div>
                </div>
                <div class="dice-read" id="dice-read">Awaiting snap…</div>
            </div>

            <!-- Flippy Deck -->
            <div class="flippy" data-testid="flippy-deck">
                <div class="row-between">
                    <span class="eyebrow accent">Flippy Deck</span>
                    <span class="pill mono" id="flippy-remaining">350/350</span>
                </div>
                <div class="flippy-stage">
                    <div class="flippy-card is-back" id="flippy-card">
                        <div class="flippy-face flippy-back"><div class="flippy-back-inner">Flippy<br>Deck</div></div>
                        <div class="flippy-face flippy-front" id="flippy-front">
                            <div class="flippy-front-inner">
                                <div class="mono tiny">Card says</div>
                                <div class="big-num" id="flippy-num">—</div>
                                <div class="mono tiny" id="flippy-label">Awaiting draw</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row-between mt-4">
            <button id="roll-btn" class="btn-primary" data-testid="play-next-play-btn">Roll & Play →</button>
            <button id="auto-btn" class="btn-secondary">Auto-Play</button>
        </div>
    </div>

    <div class="card">
        <h2 class="section-title">Play-By-Play</h2>
        <div class="play-feed" id="play-feed"></div>
    </div>
</section>

<script>
window.GAME_CTX = {
    seasonId: <?= json_encode($s['id']) ?>,
    gameId: <?= json_encode($game['game_id']) ?>,
    home: <?= json_encode($game['home']) ?>,
    away: <?= json_encode($game['away']) ?>,
    played: <?= $game['played'] ? 'true' : 'false' ?>,
    logId: <?= json_encode($game['log_id'] ?? '') ?>,
};
</script>
<script src="/assets/game.js"></script>
