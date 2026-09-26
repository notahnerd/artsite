<?php
require_once __DIR__ . '/../../includes/nfl_data.php';
?>
<section class="hero">
    <h1 class="display">Gridiron Roller</h1>
    <p class="lead">Pick your team. Grab the dice. Flip the deck. Win a Super Bowl.</p>
</section>

<section class="card">
    <div class="row-between">
        <h2 class="section-title">Choose Your Franchise</h2>
        <div class="pill">2025 Season</div>
    </div>

    <div class="team-grid" id="team-grid">
        <?php foreach (NflData::TEAMS as $t): ?>
        <button class="team-card"
                data-team="<?= htmlspecialchars($t['id']) ?>"
                style="--team-primary: <?= htmlspecialchars($t['primary']) ?>; --team-secondary: <?= htmlspecialchars($t['secondary']) ?>;">
            <span class="team-abbr"><?= htmlspecialchars($t['id']) ?></span>
            <span class="team-city"><?= htmlspecialchars($t['city']) ?></span>
            <span class="team-name"><?= htmlspecialchars($t['name']) ?></span>
            <span class="team-ratings">
                O <?= $t['off'] ?> · D <?= $t['def'] ?>
            </span>
        </button>
        <?php endforeach; ?>
    </div>

    <div class="row-between mt-4">
        <div>
            <label class="form-label">Difficulty</label>
            <select id="difficulty" class="input">
                <option value="arcade" selected>Arcade (fun)</option>
                <option value="balanced">Balanced</option>
                <option value="hard">Hard</option>
                <option value="legendary">Legendary</option>
            </select>
        </div>
        <button id="start-season" class="btn-primary" disabled>Select a team →</button>
    </div>
</section>

<script>
    // Auto-resume the last season if present
    (function () {
        var last = localStorage.getItem('gr_last_season');
        if (last) {
            try {
                var d = JSON.parse(last);
                if (d && d.id) {
                    var banner = document.createElement('div');
                    banner.className = 'card resume-banner';
                    banner.innerHTML = 'Resume your last season with <b>' + d.team + '</b>? '
                      + '<a href="/season/' + d.id + '" class="btn-secondary">Resume</a> '
                      + '<a href="#" id="gr-forget" class="btn-ghost">Forget</a>';
                    document.querySelector('main.stage').prepend(banner);
                    document.getElementById('gr-forget').onclick = function (e) {
                        e.preventDefault();
                        localStorage.removeItem('gr_last_season');
                        banner.remove();
                    };
                }
            } catch (e) {}
        }
    })();

    var selected = null;
    document.querySelectorAll('.team-card').forEach(function (el) {
        el.addEventListener('click', function () {
            document.querySelectorAll('.team-card.selected').forEach(function (x) { x.classList.remove('selected'); });
            el.classList.add('selected');
            selected = el.dataset.team;
            var btn = document.getElementById('start-season');
            btn.disabled = false;
            btn.textContent = 'Start Season as ' + selected + ' →';
        });
    });

    document.getElementById('start-season').addEventListener('click', async function () {
        if (!selected) return;
        this.disabled = true;
        this.textContent = 'Building schedule…';
        var difficulty = document.getElementById('difficulty').value;
        try {
            var r = await fetch('/api/season/create', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ user_team: selected, difficulty: difficulty }),
            });
            var s = await r.json();
            if (!r.ok) throw new Error(s.detail || 'Failed');
            // Persist owner token
            var tokens = {};
            try { tokens = JSON.parse(localStorage.getItem('gr_owner_tokens') || '{}'); } catch (e) {}
            tokens[s.id] = s.owner_token;
            localStorage.setItem('gr_owner_tokens', JSON.stringify(tokens));
            localStorage.setItem('gr_last_season', JSON.stringify({ id: s.id, team: selected }));
            location.href = '/season/' + s.id;
        } catch (e) {
            alert('Error: ' + e.message);
            this.disabled = false;
            this.textContent = 'Try again →';
        }
    });
</script>
