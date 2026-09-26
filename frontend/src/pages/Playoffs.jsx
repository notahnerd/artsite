import React, { useEffect, useState } from "react";
import { useParams, useNavigate } from "react-router-dom";
import { getSeason, api, listTeams } from "@/lib/api";
import { toast } from "sonner";
import Nav from "@/components/Nav";
import WeatherBadge from "@/components/WeatherBadge";
import { Trophy } from "lucide-react";

export default function Playoffs() {
  const { id } = useParams();
  const nav = useNavigate();
  const [season, setSeason] = useState(null);
  const [teamsById, setTeamsById] = useState({});
  const [simming, setSimming] = useState(null);

  const load = async () => {
    const s = await getSeason(id);
    setSeason(s);
    if (!Object.keys(teamsById).length) {
      const list = await listTeams();
      const map = {}; list.forEach((t) => { map[t.id] = t; });
      setTeamsById(map);
    }
  };

  useEffect(() => { load(); /* eslint-disable-next-line */ }, [id]);

  if (!season) return <div className="min-h-screen grid place-items-center text-slate-400 font-mono">Loading…</div>;

  const bracket = season.playoffs;
  const allDone = season.schedule.every((g) => g.played);

  const startPlayoffs = async () => {
    try {
      await api.post(`/season/${id}/start-playoffs`);
      toast.success("Playoffs bracket generated!");
      load();
    } catch (e) {
      toast.error(e?.response?.data?.detail || "Failed to start playoffs");
    }
  };

  const simGame = async (gameId, playIt = false) => {
    setSimming(gameId);
    try {
      if (playIt) {
        nav(`/season/${id}/playoff-game/${gameId}`);
        return;
      }
      await api.post("/season/playoff-game", { season_id: id, game_id: gameId });
      toast.success("Game complete");
      load();
    } catch (e) {
      toast.error(e?.response?.data?.detail || "Sim failed");
    } finally {
      setSimming(null);
    }
  };

  if (!bracket) {
    return (
      <div className="min-h-screen">
        <Nav seasonId={id} userTeam={season.user_team} />
        <div className="max-w-4xl mx-auto px-4 lg:px-8 py-10 text-center">
          <Trophy className="mx-auto text-amber-400" size={48} />
          <h1 className="mt-3 font-display font-black uppercase text-4xl">Playoffs</h1>
          {allDone ? (
            <>
              <p className="mt-3 text-slate-300">Regular season complete. Set the bracket!</p>
              <button
                onClick={startPlayoffs}
                data-testid="start-playoffs-btn"
                className="mt-6 px-6 py-3 rounded-lg bg-amber-500 hover:bg-amber-400 text-slate-900 font-display font-black uppercase tracking-wider"
              >
                Set the Bracket →
              </button>
            </>
          ) : (
            <p className="mt-3 text-slate-400">Finish the regular season first (Week {season.current_week} of 18).</p>
          )}
        </div>
      </div>
    );
  }

  const rounds = {
    WC: bracket.games.filter((g) => g.round === "WC"),
    DIV: bracket.games.filter((g) => g.round === "DIV"),
    CONF: bracket.games.filter((g) => g.round === "CONF"),
    SB: bracket.games.filter((g) => g.round === "SB"),
  };

  return (
    <div className="min-h-screen">
      <Nav seasonId={id} userTeam={season.user_team} />
      <div className="max-w-7xl mx-auto px-4 lg:px-8 py-6 space-y-6">
        <div className="flex items-center justify-between gap-4 flex-wrap">
          <div>
            <div className="text-xs font-mono uppercase tracking-[0.3em] text-amber-400">Postseason 2025</div>
            <h1 className="font-display font-black uppercase text-3xl md:text-4xl flex items-center gap-3">
              <Trophy className="text-amber-400" /> Road to the Super Bowl
            </h1>
          </div>
          {bracket.champion && (
            <div className="card-broadcast px-5 py-3 flex items-center gap-3 border-amber-400/50">
              <Trophy size={32} className="text-amber-400" />
              <div>
                <div className="text-[10px] font-mono uppercase tracking-widest text-slate-400">Super Bowl Champion</div>
                <div className="font-display font-black text-2xl uppercase" style={{ color: teamsById[bracket.champion]?.primary || "#F59E0B" }}>
                  {teamsById[bracket.champion]?.city} {teamsById[bracket.champion]?.name}
                </div>
              </div>
            </div>
          )}
        </div>

        {/* Seeds panel */}
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {["AFC", "NFC"].map((conf) => (
            <div key={conf} className="card-broadcast p-4">
              <div className="text-xs font-mono uppercase tracking-[0.3em] text-slate-400 mb-2">{conf} Seeds</div>
              <div className="space-y-1">
                {bracket.seeds[conf].map((s) => (
                  <div key={s.team} className="flex items-center gap-3 text-sm">
                    <span className="w-6 font-mono font-bold text-amber-300 tabular-nums">#{s.seed}</span>
                    <span className="w-2 h-4 rounded-sm" style={{ background: teamsById[s.team]?.primary }} />
                    <span className="font-display font-bold uppercase">{s.team}</span>
                    <span className="text-slate-400 font-mono text-xs">{teamsById[s.team]?.name}</span>
                    <span className="ml-auto font-mono text-xs text-slate-400">{s.w}-{s.l}</span>
                  </div>
                ))}
              </div>
            </div>
          ))}
        </div>

        {/* Bracket */}
        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
          {[
            ["WC", "Wild Card"],
            ["DIV", "Divisional"],
            ["CONF", "Conf Championship"],
            ["SB", "Super Bowl"],
          ].map(([key, label]) => (
            <div key={key} className="space-y-3">
              <div className="text-[10px] font-mono uppercase tracking-[0.3em] text-amber-400">{label}</div>
              {rounds[key].map((g) => (
                <BracketGame
                  key={g.id}
                  game={g}
                  teamsById={teamsById}
                  userTeam={season.user_team}
                  simming={simming === g.id}
                  onSim={(pi) => simGame(g.id, pi)}
                />
              ))}
              {rounds[key].length === 0 && (
                <div className="p-3 rounded border border-dashed border-white/10 text-center text-[10px] font-mono uppercase tracking-widest text-slate-500">
                  Awaiting matchup
                </div>
              )}
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}

function BracketGame({ game, teamsById, userTeam, simming, onSim }) {
  const home = teamsById[game.home];
  const away = teamsById[game.away];
  const pending = !game.home || !game.away;
  const involvesUser = game.home === userTeam || game.away === userTeam;
  return (
    <div
      className={`card-broadcast p-3 relative ${involvesUser ? "border-amber-500/60" : ""}`}
      data-testid={`bracket-game-${game.id}`}
    >
      <div className="flex items-center justify-between text-[10px] font-mono uppercase tracking-widest text-slate-400 mb-2">
        <span>{game.id}</span>
        {game.weather && <WeatherBadge code={game.weather} />}
      </div>
      {pending ? (
        <div className="text-slate-500 text-xs font-mono uppercase text-center py-3">
          Awaiting winners
        </div>
      ) : (
        <>
          <TeamLine team={away} seed={game.away_seed} score={game.away_score} winner={game.winner === game.away} />
          <div className="my-1 border-t border-white/5" />
          <TeamLine team={home} seed={game.home_seed} score={game.home_score} winner={game.winner === game.home} />
          {!game.played && (
            <div className="mt-2 flex gap-1.5">
              {involvesUser && (
                <button
                  onClick={() => onSim(true)}
                  disabled={simming}
                  className="flex-1 px-2 py-1.5 rounded bg-amber-500 hover:bg-amber-400 text-slate-900 font-display font-bold uppercase text-[10px] tracking-widest"
                  data-testid={`play-playoff-${game.id}`}
                >
                  Play
                </button>
              )}
              <button
                onClick={() => onSim(false)}
                disabled={simming}
                className="flex-1 px-2 py-1.5 rounded bg-white/5 hover:bg-white/10 text-slate-100 font-display font-bold uppercase text-[10px] tracking-widest border border-white/10 disabled:opacity-50"
                data-testid={`sim-playoff-${game.id}`}
              >
                {simming ? "…" : "Sim"}
              </button>
            </div>
          )}
        </>
      )}
    </div>
  );
}

function TeamLine({ team, seed, score, winner }) {
  if (!team) return null;
  return (
    <div className={`flex items-center gap-2 text-sm ${winner ? "" : "opacity-90"}`}>
      <span className="text-[10px] font-mono text-slate-500 tabular-nums w-5">#{seed}</span>
      <span className="w-2 h-5 rounded-sm shrink-0" style={{ background: team.primary }} />
      <span className={`font-display font-bold uppercase truncate ${winner ? "text-amber-300" : ""}`}>{team.id}</span>
      <span className="text-slate-400 text-xs truncate">{team.name}</span>
      <span className="ml-auto font-mono font-bold tabular-nums w-6 text-right">{score ?? "-"}</span>
    </div>
  );
}
