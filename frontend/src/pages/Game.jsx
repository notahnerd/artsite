import React, { useEffect, useMemo, useRef, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import { getSeason, getTeam, simGame } from "@/lib/api";
import { toast } from "sonner";
import Nav from "@/components/Nav";
import Scoreboard from "@/components/Scoreboard";
import Field from "@/components/Field";
import PlayByPlay from "@/components/PlayByPlay";
import BoxScore from "@/components/BoxScore";
import Dice from "@/components/Dice";

export default function Game() {
  const { id, gameId } = useParams();
  const nav = useNavigate();
  const [teamsById, setTeamsById] = useState({});
  const [game, setGame] = useState(null); // schedule entry
  const [playLog, setPlayLog] = useState([]); // fetched plays
  const [visiblePlays, setVisiblePlays] = useState([]); // played out so far
  const [state, setState] = useState(null); // current derived state
  const [stats, setStats] = useState(null);
  const [rolling, setRolling] = useState(false);
  const [finished, setFinished] = useState(false);
  const [autoPlay, setAutoPlay] = useState(false);
  const [initialLoading, setInitialLoading] = useState(true);
  const [season, setSeason] = useState(null);
  const rollTimerRef = useRef(null);
  const autoTimerRef = useRef(null);

  useEffect(() => {
    (async () => {
      const s = await getSeason(id);
      setSeason(s);
      const g = s.schedule.find((x) => x.game_id === gameId);
      setGame(g);
      const details = await Promise.all([g.home, g.away].map(getTeam));
      const map = {}; details.forEach((d) => { map[d.team.id] = d.team; });
      setTeamsById(map);

      // Full simulate once (we drive the reveal client-side for animation)
      const result = await simGame(id, gameId);
      const plays = result?.result?.plays || [];
      setPlayLog(plays);
      // Set initial pre-play state
      setState({
        home: g.home, away: g.away,
        [`${g.home}_score`]: 0, [`${g.away}_score`]: 0,
        quarter: 1, clock: 900,
        down: 1, distance: 10, ball_on: 25,
        possession: g.away,
      });
      setStats({ [g.home]: emptyStats(), [g.away]: emptyStats() });
      setInitialLoading(false);
    })().catch((e) => {
      console.error(e);
      toast.error("Failed to load game");
    });
    return () => {
      clearTimeout(rollTimerRef.current);
      clearTimeout(autoTimerRef.current);
    };
    // eslint-disable-next-line
  }, [id, gameId]);

  const nextPlay = () => {
    if (finished) return;
    const idx = visiblePlays.length;
    if (idx >= playLog.length) {
      setFinished(true);
      return;
    }
    setRolling(true);
    const play = playLog[idx];
    // announce big events
    if (play.result === "TD") toast.success(`TOUCHDOWN ${teamsById[play.off]?.name || play.off}!`);
    if (play.result === "INT") toast.error("INTERCEPTION!");
    if (play.result === "FUMBLE") toast.error("FUMBLE!");
    if (play.result === "FG_GOOD") toast.success("Field Goal is GOOD!");

    rollTimerRef.current = setTimeout(() => {
      setRolling(false);
      setVisiblePlays((p) => [...p, play]);
      setState((prev) => applyPlayToState(prev, play));
      setStats((s) => applyPlayToStats(s, play));
      if (idx + 1 >= playLog.length) setFinished(true);
    }, 750);
  };

  useEffect(() => {
    if (autoPlay && !finished && !rolling && playLog.length && visiblePlays.length < playLog.length) {
      autoTimerRef.current = setTimeout(nextPlay, 900);
    }
    return () => clearTimeout(autoTimerRef.current);
    // eslint-disable-next-line
  }, [autoPlay, rolling, visiblePlays.length, playLog.length, finished]);

  const last = visiblePlays[visiblePlays.length - 1];
  const displayDice = last ? last.dice : [1, 1];

  if (initialLoading || !state || !game) {
    return (
      <div className="min-h-screen grid place-items-center text-slate-400 font-mono uppercase tracking-widest">
        <div className="flex items-center gap-3">
          <span className="w-3 h-3 rounded-full bg-amber-500 pulse-glow" /> Warming up the stadium…
        </div>
      </div>
    );
  }

  const home = teamsById[game.home];
  const away = teamsById[game.away];
  if (!home || !away) return null;

  return (
    <div className="min-h-screen">
      <Nav seasonId={id} userTeam={season?.user_team} />
      <div className="max-w-[1500px] mx-auto px-3 lg:px-6 py-4 md:py-6 space-y-4">
        <Scoreboard state={state} home={home} away={away} />
        <Field state={state} home={home} away={away} />

        <div className="grid grid-cols-1 lg:grid-cols-12 gap-4">
          {/* Dice + Controls */}
          <div className="lg:col-span-4 space-y-4">
            <div className="card-broadcast p-5">
              <div className="text-[10px] font-mono uppercase tracking-widest text-slate-400 mb-3">
                2D6 CHART ROLL
              </div>
              <Dice values={displayDice} rolling={rolling} />
              {last && (
                <div className="mt-4 pt-4 border-t border-white/5">
                  <div className="text-[10px] font-mono uppercase tracking-widest text-slate-500 mb-1">
                    Last Result • {last.play_type}
                  </div>
                  <div className="text-sm text-slate-200 leading-snug">{last.description}</div>
                </div>
              )}
              <div className="mt-5 flex gap-2">
                <button
                  data-testid="play-next-play-btn"
                  onClick={nextPlay}
                  disabled={rolling || finished || autoPlay}
                  className="flex-1 px-4 py-3 rounded-md bg-amber-500 hover:bg-amber-400 text-slate-900 font-display font-black uppercase tracking-wider disabled:opacity-40 transition-colors"
                >
                  {rolling ? "Rolling…" : finished ? "Final" : "Roll & Play"}
                </button>
                <button
                  data-testid="auto-sim-game-btn"
                  onClick={() => setAutoPlay((v) => !v)}
                  disabled={finished}
                  className={`px-4 py-3 rounded-md font-display font-bold uppercase tracking-wider text-sm border transition-colors ${
                    autoPlay ? "bg-rose-500/20 text-rose-300 border-rose-500/40" : "bg-white/5 text-slate-200 border-white/10 hover:bg-white/10"
                  }`}
                >
                  {autoPlay ? "Stop" : "Auto"}
                </button>
              </div>
              {finished && (
                <button
                  onClick={() => nav(`/season/${id}`)}
                  data-testid="back-to-season-btn"
                  className="mt-3 w-full px-4 py-2 rounded-md bg-white/5 hover:bg-white/10 text-slate-100 font-mono uppercase tracking-widest text-xs border border-white/10"
                >
                  Back to Season →
                </button>
              )}
            </div>
            <BoxScore stats={stats} home={home} away={away} />
          </div>

          {/* Play by play */}
          <div className="lg:col-span-8">
            <PlayByPlay plays={visiblePlays} teamsById={teamsById} />
          </div>
        </div>
      </div>
    </div>
  );
}

function emptyStats() {
  return { total_yards: 0, pass_yards: 0, rush_yards: 0, turnovers: 0, sacks_allowed: 0, first_downs: 0, plays: 0, tds: 0, fgs: 0 };
}

function applyPlayToState(prev, play) {
  const next = { ...prev, quarter: play.quarter, clock: play.clock };
  // We rely on incoming play "off/def/ball_on" to reflect PRE-play state; use play as-is for state view
  // Instead, project a post-play view directly:
  // For simplicity, we update scores based on score_change, and ball position best-effort.
  if (play.score_change) {
    next[`${play.score_change.team}_score`] =
      (prev[`${play.score_change.team}_score`] || 0) + play.score_change.points;
  }
  // If TD/turnover the possession flips
  if (play.result === "TD" || play.result === "INT" || play.result === "FUMBLE" || play.result === "PUNT" || play.result === "FG_GOOD" || play.result === "FG_MISS") {
    next.possession = play.def;
    next.ball_on = 25;
    next.down = 1;
    next.distance = 10;
  } else {
    next.possession = play.off;
    const newBall = Math.max(1, Math.min(99, play.ball_on + play.yards));
    next.ball_on = newBall;
    if (play.yards >= play.distance) {
      next.down = 1; next.distance = 10;
    } else {
      next.down = Math.min(4, play.down + 1);
      next.distance = Math.max(1, play.distance - play.yards);
    }
  }
  return next;
}

function applyPlayToStats(stats, play) {
  const off = play.off;
  const s = { ...stats[off] };
  s.plays += 1;
  if (play.result === "SACK") { s.sacks_allowed += 1; s.pass_yards += play.yards; }
  else if (play.play_type === "PASS" && !["INT","INCOMPLETE"].includes(play.result)) s.pass_yards += play.yards;
  else if (play.play_type === "RUN") s.rush_yards += play.yards;
  if (!["PUNT","FG_GOOD","FG_MISS","INCOMPLETE","INT"].includes(play.result)) s.total_yards += play.yards;
  if (play.turnover) s.turnovers += 1;
  if (play.result === "TD") s.tds += 1;
  if (play.result === "FG_GOOD") s.fgs += 1;
  if (play.yards >= play.distance && !["INT","FUMBLE"].includes(play.result)) s.first_downs += 1;
  return { ...stats, [off]: s };
}
