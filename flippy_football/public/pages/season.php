<?php
/** @var array $pageParams */
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/season.php';
require_once __DIR__ . '/../../includes/nfl_data.php';

$stmt = db()->prepare('SELECT * FROM seasons WHERE id = ? LIMIT 1');
$stmt->execute([$pageParams['season_id']]);
$row = $stmt->fetch();
if (!$row) { echo '<div class="card"><h1>Season not found</h1></div>'; return; }
$season = decode_season($row);
$standings = Season::computeStandings($season['schedule']);
$currentWeek = (int)$season['current_week'];
$upcoming = array_values(array_filter($season['schedule'], static fn($g) => (int)$g['week'] === $currentWeek));
$userTeam = $season['user_team'];
$myGame = null;
foreach ($upcoming as $g) if ($g['home'] === $userTeam || $g['away'] === $userTeam) { $myGame = $g; break; }
?>
<section class="row-2col">
    <div class="card">
        <div class="row-between">
            <div>
                <div class="eyebrow">Season <?= htmlspecialchars((string)$season['year']) ?> · Week <?= $currentWeek ?></div>
                <h1 class="display">Your Franchise: <span class="accent"><?= htmlspecialchars($userTeam) ?></span></h1>
            </div>
            <div class="pill"><?= htmlspecialchars($season['difficulty']) ?></div>
        </div>

        <?php if ($myGame): ?>
        <div class="matchup-panel">
            <div class="matchup-side <?= $myGame['home'] === $userTeam ? 'home' : 'away' ?>">
                <div class="team-abbr"><?= htmlspecialchars($myGame['away']) ?></div>
                <div class="team-role">AWAY</div>
            </div>
            <div class="matchup-mid">
                <div class="week-label">Week <?= $myGame['week'] ?></div>
                <div class="vs">@</div>
                <div class="weather">
                    <?php $wi = Weather::info($myGame['weather'] ?? 'CLEAR'); ?>
                    <span class="weather-badge" style="--w-color: <?= htmlspecialchars($wi['color']) ?>">
                        <?= htmlspecialchars($wi['label']) ?>
                    </span>
                </div>
                <?php if (!empty($myGame['rivalry'])): ?>
                    <div class="rivalry-badge">RIVALRY GAME</div>
                <?php endif; ?>
            </div>
            <div class="matchup-side <?= $myGame['home'] === $userTeam ? 'away' : 'home' ?>">
                <div class="team-abbr"><?= htmlspecialchars($myGame['home']) ?></div>
                <div class="team-role">HOME</div>
            </div>
        </div>
        <div class="row-between mt-4">
            <?php if (!$myGame['played']): ?>
                <a href="/season/<?= htmlspecialchars($season['id']) ?>/game/<?= htmlspecialchars($myGame['game_id']) ?>" class="btn-primary">Play This Game →</a>
            <?php else: ?>
                <div class="score-panel">Final: <b><?= (int)$myGame['home_score'] ?> - <?= (int)$myGame['away_score'] ?></b></div>
            <?php endif; ?>
            <button class="btn-secondary" id="sim-week-btn">Sim Rest of Week</button>
        </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2 class="section-title">Standings — Top 8</h2>
        <table class="standings">
            <thead>
                <tr><th></th><th>Team</th><th>W</th><th>L</th><th>T</th><th>PF</th><th>PA</th><th>Diff</th></tr>
            </thead>
            <tbody>
            <?php foreach (array_slice($standings, 0, 8) as $i => $s): ?>
                <tr class="<?= $s['team'] === $userTeam ? 'me' : '' ?>">
                    <td class="mono"><?= $i + 1 ?></td>
                    <td class="bold"><?= htmlspecialchars($s['team']) ?></td>
                    <td><?= $s['w'] ?></td><td><?= $s['l'] ?></td><td><?= $s['t'] ?></td>
                    <td><?= $s['pf'] ?></td><td><?= $s['pa'] ?></td>
                    <td class="<?= $s['diff'] >= 0 ? 'plus' : 'minus' ?>"><?= $s['diff'] >= 0 ? '+' : '' ?><?= $s['diff'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="card mt-4">
    <h2 class="section-title">Week <?= $currentWeek ?> Schedule</h2>
    <div class="schedule-grid">
        <?php foreach ($upcoming as $g): ?>
        <a class="schedule-row <?= $g['played'] ? 'played' : '' ?>" href="/season/<?= htmlspecialchars($season['id']) ?>/game/<?= htmlspecialchars($g['game_id']) ?>">
            <span class="side"><?= htmlspecialchars($g['away']) ?></span>
            <span class="mid">@</span>
            <span class="side"><?= htmlspecialchars($g['home']) ?></span>
            <?php if ($g['played']): ?>
                <span class="score"><?= (int)$g['home_score'] ?> - <?= (int)$g['away_score'] ?></span>
            <?php else: ?>
                <span class="score idle">— · —</span>
            <?php endif; ?>
            <?php if (!empty($g['rivalry'])): ?><span class="tag rivalry">RIV</span><?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>
</section>

<script>
    document.getElementById('sim-week-btn')?.addEventListener('click', async function () {
        this.disabled = true;
        this.textContent = 'Simming week…';
        try {
            var tokens = JSON.parse(localStorage.getItem('gr_owner_tokens') || '{}');
            var r = await fetch('/api/season/sim-week', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Owner-Token': tokens[<?= json_encode($season['id']) ?>] || '',
                },
                body: JSON.stringify({ season_id: <?= json_encode($season['id']) ?> }),
            });
            var d = await r.json();
            if (!r.ok) throw new Error(d.detail || 'Failed');
            location.reload();
        } catch (e) {
            alert('Error: ' + e.message);
            this.disabled = false;
            this.textContent = 'Sim Rest of Week';
        }
    });
</script>
