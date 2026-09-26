<?php
/** @var array $pageParams */
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/playoffs.php';

$stmt = db()->prepare('SELECT * FROM seasons WHERE id = ?');
$stmt->execute([$pageParams['season_id']]);
$row = $stmt->fetch();
if (!$row) { echo '<div class="card"><h1>Season not found</h1></div>'; return; }
$s = decode_season($row);
$b = $s['playoffs'];
?>
<section class="card">
    <h1 class="display">Playoffs</h1>
    <?php if (!$b): ?>
        <p>Playoffs haven't started yet. Finish the regular season first.</p>
        <button id="start-playoffs" class="btn-primary">Start Playoffs</button>
        <script>
            document.getElementById('start-playoffs').addEventListener('click', async function () {
                this.disabled = true;
                var tokens = JSON.parse(localStorage.getItem('gr_owner_tokens') || '{}');
                var r = await fetch('/api/season/<?= htmlspecialchars($s['id']) ?>/start-playoffs', {
                    method: 'POST',
                    headers: { 'X-Owner-Token': tokens[<?= json_encode($s['id']) ?>] || '' },
                });
                var d = await r.json();
                if (!r.ok) { alert(d.detail); this.disabled = false; return; }
                location.reload();
            });
        </script>
    <?php else: ?>
        <div class="bracket">
            <?php foreach ([['WC','Wild Card'],['DIV','Divisional'],['CONF','Conf. Championship'],['SB','Super Bowl']] as [$r, $label]): ?>
                <div class="bracket-column">
                    <h3 class="eyebrow"><?= $label ?></h3>
                    <?php foreach (array_filter($b['games'], static fn($g) => $g['round'] === $r) as $g): ?>
                        <div class="bracket-game <?= $g['played'] ? 'played' : '' ?> <?= $g['winner'] ? 'has-winner' : '' ?>">
                            <div class="bg-row <?= $g['winner'] === $g['home'] ? 'winner' : '' ?>">
                                <span class="seed"><?= $g['home_seed'] ? '#'.$g['home_seed'] : '' ?></span>
                                <span class="team"><?= htmlspecialchars($g['home'] ?? 'TBD') ?></span>
                                <span class="score"><?= $g['played'] ? (int)$g['home_score'] : '—' ?></span>
                            </div>
                            <div class="bg-row <?= $g['winner'] === $g['away'] ? 'winner' : '' ?>">
                                <span class="seed"><?= $g['away_seed'] ? '#'.$g['away_seed'] : '' ?></span>
                                <span class="team"><?= htmlspecialchars($g['away'] ?? 'TBD') ?></span>
                                <span class="score"><?= $g['played'] ? (int)$g['away_score'] : '—' ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if ($b['champion']): ?>
            <div class="champion-banner">🏆 Champion: <b><?= htmlspecialchars($b['champion']) ?></b></div>
        <?php endif; ?>
    <?php endif; ?>
</section>
