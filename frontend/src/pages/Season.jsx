import React, { useEffect, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import { getSeason, getTeam, simGame, simWeek } from "@/lib/api";
import { toast } from "sonner";
import Nav from "@/components/Nav";
import PlayerCard from "@/components/PlayerCard";
import WeatherBadge from "@/components/WeatherBadge";
import DepthChartDialog from "@/components/DepthChartDialog";
import { Settings } from "lucide-react";

export default function Season() {
  const { id } = useParams();
  const nav = useNavigate();
  const [season, setSeason] = useState(null);
  const [teamsById, setTeamsById] = useState({});
  const [roster, setRoster] = useState(null);
  const [simmingWeek, setSimmingWeek] = useState(false);
  const [depthOpen, setDepthOpen] = useState(false);

  const load = async () => {
    const s = await getSeason(id);
    setSeason(s);
    if (!Object.keys(teamsById).length) {
      const map = {};
      const details = await Promise.all(s.standings.map((st) => getTeam(st.team)));
      details.forEach((d) => { map[d.team.id] = d.team; });
      setTeamsById(map);
      const my = details.find((d) => d.team.id === s.user_team);
      setRoster(my);
    }
  };

  useEffect(() => { load(); /* eslint-disable-next-line */ }, [id]);

  if (!season) return <div className="min-h-screen grid place-items-center text-slate-400 font-mono uppercase tracking-widest">Loading season…</div>;

  const currentWeek = Math.min(18, season.current_week);
  const isPlayoffsReady = season.schedule.every((g) => g.played);
  const weekGames = season.schedule.filter((g) => g.week === currentWeek);
  const userGame = weekGames.find((g) => g.home === season.user_team || g.away === season.user_team);

  const playUserGame = async () => {
    if (!userGame) return;
    // Navigate to interactive game screen
    nav(`/season/${id}/game/${userGame.game_id}`);
  };

  const doSimWeek = async () => {
    setSimmingWeek(true);
    try {
      await simWeek(id, currentWeek);
      toast.success(`Week ${currentWeek} simulated`);
      await load();
    } catch (e) {
      toast.error("Sim failed");
    } finally {
      setSimmingWeek(false);
    }
  };

  return (
    <div className="min-h-screen">
      <Nav seasonId={id} userTeam={season.user_team} />
      <div className="max-w-7xl mx-auto px-4 lg:px-8 py-6 md:py-8 space-y-6">
        <div className="flex items-center justify-between flex-wrap gap-3">
          <div>
            <div className="text-xs font-mono uppercase tracking-[0.3em] text-amber-400">Season 2025 • Week {currentWeek}</div>
            <h1 className="font-display font-black uppercase text-3xl md:text-4xl">Season Command</h1>
          </div>
          <div className="flex items-center gap-2">
            {isPlayoffsReady && (
              <button
                onClick={() => nav(`/season/${id}/playoffs`)}
                data-testid="goto-playoffs-btn"
                className="px-4 py-2 rounded-md bg-amber-500 hover:bg-amber-400 text-slate-900 font-display font-black uppercase tracking-wider transition-colors"
              >
                Enter Playoffs →
              </button>
            )}
            {userGame && !userGame.played && !isPlayoffsReady && (
              <button
                data-testid="play-my-game-btn"
                onClick={playUserGame}
                className="px-4 py-2 rounded-md bg-amber-500 hover:bg-amber-400 text-slate-900 font-display font-black uppercase tracking-wider transition-colors"
              >
                Play My Game →
              </button>
            )}
            {!isPlayoffsReady && (
              <button
                data-testid="sim-week-btn"
                onClick={doSimWeek}
                disabled={simmingWeek || currentWeek > 18}
                className="px-4 py-2 rounded-md bg-white/10 hover:bg-white/15 text-slate-100 font-display font-bold uppercase tracking-wider text-sm border border-white/10 disabled:opacity-40 transition-colors"
              >
                {simmingWeek ? "Simming…" : "Sim Week (Auto)"}
              </button>
            )}
          </div>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-12 gap-4">
          {/* Week Schedule */}
          <div className="lg:col-span-8 card-broadcast p-5">
            <div className="flex items-center justify-between mb-4">
              <h2 className="font-display font-extrabold uppercase text-xl tracking-wide">Week {currentWeek} Schedule</h2>
              <span className="text-[10px] font-mono uppercase tracking-widest text-slate-400">
                {weekGames.filter((g) => g.played).length}/{weekGames.length} played
              </span>
            </div>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-2.5">
              {weekGames.map((g) => (
                <GameRow
                  key={g.game_id}
                  game={g}
                  teamsById={teamsById}
                  userTeam={season.user_team}
                  onPlay={() => nav(`/season/${id}/game/${g.game_id}`)}
                />
              ))}
            </div>
          </div>

          {/* Standings */}
          <div className="lg:col-span-4 card-broadcast p-5">
            <h2 className="font-display font-extrabold uppercase text-xl tracking-wide mb-3">Standings</h2>
            <StandingsTable standings={season.standings} teamsById={teamsById} userTeam={season.user_team} />
          </div>
        </div>

        {/* Roster */}
        {roster && (
          <div className="card-broadcast p-5">
            <div className="flex items-center gap-3 mb-4">
              <div
                className="w-10 h-10 rounded grid place-items-center font-display font-black text-white"
                style={{ background: roster.team.primary }}
              >
                {roster.team.id}
              </div>
              <div>
                <div className="text-[10px] font-mono uppercase tracking-widest text-slate-400">Your Roster</div>
                <h2 className="font-display font-extrabold uppercase text-xl">
                  {roster.team.city} {roster.team.name}
                </h2>
              </div>
              <div className="ml-auto flex items-center gap-3 text-xs font-mono uppercase tracking-widest">
                <span>OFF <span className="text-amber-400 font-bold">{roster.team.off}</span></span>
                <span>DEF <span className="text-amber-400 font-bold">{roster.team.def}</span></span>
                <span>ST  <span className="text-amber-400 font-bold">{roster.team.st}</span></span>
                <button
                  onClick={() => setDepthOpen(true)}
                  data-testid="open-depth-chart-btn"
                  className="ml-2 px-3 py-1.5 rounded bg-amber-500 hover:bg-amber-400 text-slate-900 font-display font-bold uppercase text-[10px] tracking-widest flex items-center gap-1"
                >
                  <Settings size={12} /> Depth Chart
                </button>
              </div>
            </div>
            <table className="w-full text-sm" data-testid="roster-table">
              <thead>
                <tr className="text-[10px] font-mono uppercase tracking-widest text-slate-400 text-left">
                  <th className="py-2">#</th>
                  <th>Player</th>
                  <th>POS</th>
                  <th className="text-right pr-2">OVR</th>
                </tr>
              </thead>
              <tbody>
                {roster.players.map((p) => (
                  <PlayerCard key={p.name} team={roster.team} player={p}>
                    <tr
                      className="border-t border-white/5 hover:bg-white/5 cursor-pointer"
                      data-testid={`roster-row-${p.name.replace(/\s+/g,'-')}`}
                    >
                      <td className="py-2 font-mono text-slate-400">{p.num}</td>
                      <td className="font-semibold group">
                        <span className="group-hover:text-amber-300">{p.name}</span>
                        <span className="ml-2 text-[9px] font-mono uppercase tracking-widest text-slate-500 group-hover:text-amber-400">card →</span>
                      </td>
                      <td className="text-slate-300 font-mono text-xs">{p.pos}</td>
                      <td className={`text-right pr-2 font-mono font-bold ${p.ovr >= 92 ? "text-emerald-300" : p.ovr >= 85 ? "text-amber-300" : "text-slate-200"}`}>{p.ovr}</td>
                    </tr>
                  </PlayerCard>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {roster?.team && (
          <DepthChartDialog
            seasonId={id}
            team={roster.team}
            open={depthOpen}
            onOpenChange={setDepthOpen}
            onSaved={load}
          />
        )}
      </div>
    </div>
  );
}

function GameRow({ game, teamsById, userTeam, onPlay }) {
  const home = teamsById[game.home];
  const away = teamsById[game.away];
  if (!home || !away) return null;
  const isUser = game.home === userTeam || game.away === userTeam;
  return (
    <div
      className={`p-3 rounded-lg border flex items-center justify-between gap-3 ${
        isUser ? "border-amber-500/60 bg-amber-500/5" : "border-white/5 bg-[#0A0F1D]/40"
      }`}
      data-testid={`game-row-${game.game_id}`}
    >
      <div className="flex-1 min-w-0">
        <div className="flex items-center gap-2 text-sm">
          <span className="w-2 h-6 rounded-sm shrink-0" style={{ background: away.primary }} />
          <span className="font-display font-bold uppercase truncate">{away.id} {away.name}</span>
          <span className="ml-auto font-mono font-bold text-slate-300 tabular-nums w-6 text-right">
            {game.played ? game.away_score : "-"}
          </span>
        </div>
        <div className="flex items-center gap-2 text-sm mt-1">
          <span className="w-2 h-6 rounded-sm shrink-0" style={{ background: home.primary }} />
          <span className="font-display font-bold uppercase truncate">{home.id} {home.name}</span>
          <span className="ml-auto font-mono font-bold text-slate-300 tabular-nums w-6 text-right">
            {game.played ? game.home_score : "-"}
          </span>
        </div>
        {game.weather && (
          <div className="mt-1.5">
            <WeatherBadge code={game.weather} />
          </div>
        )}
      </div>
      {!game.played && (
        <button
          onClick={onPlay}
          className="text-[10px] font-mono uppercase tracking-widest px-2 py-1 rounded bg-amber-500/20 text-amber-300 hover:bg-amber-500/30 border border-amber-500/30 transition-colors"
        >
          {isUser ? "Play" : "Sim"}
        </button>
      )}
      {game.played && (
        <span className="text-[10px] font-mono uppercase tracking-widest text-slate-500">
          {game.home_score > game.away_score ? `${home.id} W` : game.home_score < game.away_score ? `${away.id} W` : "TIE"}
        </span>
      )}
    </div>
  );
}

function StandingsTable({ standings, teamsById, userTeam }) {
  return (
    <div className="overflow-hidden" data-testid="standings-table">
      <table className="w-full text-sm">
        <thead>
          <tr className="text-[10px] font-mono uppercase tracking-widest text-slate-400 text-left">
            <th className="py-1.5">TEAM</th>
            <th className="text-right pr-2">W</th>
            <th className="text-right pr-2">L</th>
            <th className="text-right pr-2">PCT</th>
            <th className="text-right pr-1">DIFF</th>
          </tr>
        </thead>
        <tbody>
          {standings.slice(0, 16).map((s, i) => {
            const t = teamsById[s.team];
            const isUser = s.team === userTeam;
            return (
              <tr key={s.team} className={`border-t border-white/5 ${isUser ? "bg-amber-500/10" : ""}`}>
                <td className="py-1.5 flex items-center gap-2">
                  <span className="text-slate-500 font-mono text-xs w-4 tabular-nums">{i + 1}</span>
                  <span className="w-1.5 h-4 rounded-sm" style={{ background: t?.primary || "#334155" }} />
                  <span className="font-display font-bold uppercase text-sm">{s.team}</span>
                </td>
                <td className="text-right pr-2 font-mono tabular-nums">{s.w}</td>
                <td className="text-right pr-2 font-mono tabular-nums">{s.l}</td>
                <td className="text-right pr-2 font-mono tabular-nums text-slate-300">{s.pct.toFixed(3)}</td>
                <td className={`text-right pr-1 font-mono tabular-nums ${s.diff >= 0 ? "text-emerald-400" : "text-rose-400"}`}>
                  {s.diff >= 0 ? "+" : ""}{s.diff}
                </td>
              </tr>
            );
          })}
        </tbody>
      </table>
    </div>
  );
}
