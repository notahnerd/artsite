/**
 * game.js — vanilla JS engine for the live-play page.
 * Fetches a full game log from the sim endpoint, then paces through plays with
 * dice rolls and Flippy Deck flip animations.
 */
(function () {
    const ctx = window.GAME_CTX;
    const $ = (id) => document.getElementById(id);
    const feed = $('play-feed');
    const rollBtn = $('roll-btn');
    const autoBtn = $('auto-btn');

    const state = {
        plays: [],
        idx: 0,
        auto: false,
        homeScore: 0,
        awayScore: 0,
    };

    function ownerToken() {
        try {
            const tokens = JSON.parse(localStorage.getItem('gr_owner_tokens') || '{}');
            return tokens[ctx.seasonId] || '';
        } catch (e) { return ''; }
    }

    async function loadOrSim() {
        if (ctx.played && ctx.logId) {
            const r = await fetch(`/api/game-log/${ctx.logId}`);
            const d = await r.json();
            state.plays = d.plays || [];
            rollBtn.textContent = 'Replay Next Play';
        } else {
            rollBtn.disabled = true;
            rollBtn.textContent = 'Simming…';
            const r = await fetch('/api/season/sim-game', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Owner-Token': ownerToken() },
                body: JSON.stringify({ season_id: ctx.seasonId, game_id: ctx.gameId }),
            });
            const d = await r.json();
            if (!r.ok) {
                alert(d.detail || 'Sim failed');
                rollBtn.textContent = 'Sim failed';
                return;
            }
            state.plays = d.result.plays || [];
            ctx.logId = d.log_id;
            ctx.played = true;
            rollBtn.disabled = false;
            rollBtn.textContent = 'Roll & Play →';
        }
    }

    // ---- Renderers ----
    function renderScoreboard(play) {
        if (!play) return;
        $('q').textContent = play.quarter;
        const mins = Math.floor((play.clock || 0) / 60);
        const secs = (play.clock || 0) % 60;
        $('clock').textContent = `${mins}:${String(secs).padStart(2, '0')}`;
        const downOrd = ['1st', '2nd', '3rd', '4th'][play.down - 1] || play.down + 'th';
        $('down').textContent = downOrd;
        $('dist').textContent = play.distance;
        $('ballon').textContent = play.ball_on;
        // score update
        if (play.score_change) {
            if (play.score_change.team === ctx.home) state.homeScore += play.score_change.points;
            else state.awayScore += play.score_change.points;
            $('score-home').textContent = state.homeScore;
            $('score-away').textContent = state.awayScore;
        }
    }

    function rollDice(play) {
        const d1 = $('d1'), d2 = $('d2'), d3 = $('d3'), dr = $('dr');
        [d1, d2, d3, dr].forEach(el => el.classList.add('rolling'));
        setTimeout(() => {
            d1.textContent = play.dice ? play.dice[0] : '?';
            d2.textContent = play.dice ? play.dice[1] : '?';
            d3.textContent = play.dice ? play.dice[2] : '?';
            dr.textContent = play.red || '?';
            $('dice-read').textContent = play.read === 'DEF' ? 'DEFENSE READ' : 'OFFENSE READ';
            [d1, d2, d3, dr].forEach(el => el.classList.remove('rolling'));
        }, 400);
    }

    function flipDeckCard(card) {
        const cardEl = $('flippy-card');
        const front = $('flippy-front');
        const num = $('flippy-num');
        const label = $('flippy-label');
        cardEl.classList.remove('is-face');
        cardEl.classList.add('is-back');

        setTimeout(() => {
            front.className = 'flippy-face flippy-front';
            const inner = front.querySelector('.flippy-front-inner');
            inner.classList.remove('dice', 'injury', 'signature', 'negative', 'big');
            if (!card) {
                num.textContent = '—';
                label.textContent = 'No card';
            } else if (card.type === 'DICE') {
                inner.classList.add('dice');
                num.textContent = 'ROLL';
                label.textContent = 'Roll the dice!';
            } else if (card.type === 'INJURY') {
                inner.classList.add('injury');
                const inj = card.injury || {};
                num.textContent = 'OUT';
                label.textContent = (inj.player || 'Player') + ' (' + (inj.pos || '?') + ') — Concussion';
            } else {
                // YARDS
                const y = card.yards ?? 0;
                if (card.signature) inner.classList.add('signature');
                else if (y < 0) inner.classList.add('negative');
                else if (y >= 15) inner.classList.add('big');
                num.textContent = y > 0 ? '+' + y : y;
                label.textContent = card.signature
                    ? (card.label || 'Signature')
                    : (y >= 30 ? 'BREAKAWAY' : y >= 15 ? 'Chunk' : y < 0 ? 'Loss' : 'Yards');
            }
            if (card && typeof card.remaining === 'number') {
                $('flippy-remaining').textContent = card.remaining + '/350';
            }
            cardEl.classList.remove('is-back');
            cardEl.classList.add('is-face');
        }, 320);
    }

    function appendPlay(play) {
        const item = document.createElement('div');
        item.className = 'play-item ' + (play.result || '') + (play.card && play.card.signature ? ' card-signature' : '');
        const chip = play.card && play.card.type === 'YARDS'
            ? ` <span class="pill mono" style="margin-left:8px">✨ ${play.card.yards > 0 ? '+' : ''}${play.card.yards} card</span>`
            : '';
        item.innerHTML = `
            <div class="meta">Q${play.quarter} · ${play.play_type} · roll ${play.roll} · read ${play.read}${chip}</div>
            <div>${play.description || ''}</div>
        `;
        feed.prepend(item);
        // Cap the feed length
        while (feed.children.length > 40) feed.removeChild(feed.lastChild);
    }

    async function nextPlay() {
        if (state.idx >= state.plays.length) {
            rollBtn.disabled = true;
            rollBtn.textContent = 'Game Over';
            state.auto = false;
            autoBtn.textContent = 'Auto-Play';
            return;
        }
        const play = state.plays[state.idx++];
        rollDice(play);
        if (play.card) flipDeckCard(play.card);
        renderScoreboard(play);
        setTimeout(() => appendPlay(play), 600);
    }

    rollBtn.addEventListener('click', nextPlay);
    autoBtn.addEventListener('click', () => {
        state.auto = !state.auto;
        autoBtn.textContent = state.auto ? 'Stop Auto' : 'Auto-Play';
        (function tick() {
            if (!state.auto) return;
            nextPlay();
            if (state.idx < state.plays.length) setTimeout(tick, 900);
        })();
    });

    // ---- Boot ----
    (async function boot() {
        await loadOrSim();
        rollBtn.disabled = false;
    })();
})();
