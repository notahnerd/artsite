import React, { useEffect, useState } from "react";
import { useParams } from "react-router-dom";
import { api, listTeams } from "@/lib/api";
import { Trophy } from "lucide-react";

export default function Share() {
  const { logId } = useParams();
  const [data, setData] = useState(null);
  const [teamsById, setTeamsById] = useState({});

  useEffect(() => {
    api.get(`/game-log/${logId}/share`).then((r) => setData(r.data));
    listTeams().then((list) => {
      const map = {}; list.forEach((t) => { map[t.id] = t; });
      setTeamsById(map);
    });
  }, [logId]);

  if (!data) return <div className="min-h-screen grid place-items-center text-slate-400 font-mono">Loading recap…</div>;
  const home = teamsById[data.home];
  const away = teamsById[data.away];
  const homeWon = data.home_score > data.away_score;

  return (
    <div className="min-h-screen stadium-glow">
      <div className="max-w-4xl mx-auto px-4 lg:px-8 py-10 space-y-6">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-2.5">
            <div className="w-9 h-9 rounded-md grid place-items-center bg-amber-500 text-slate-900 font-display font-black text-lg">GR</div>
            <div className="font-display font-black uppercase text-lg">Gridiron Roller — Game Recap</div>
          </div>
          <a href="/" className="text-xs font-mono uppercase tracking-widest text-slate-400 hover:text-amber-400">Start Your Own →</a>
        </div>

        {/* Score card */}
        <div className="card-broadcast p-6 relative overflow-hidden">
          <div className="grid grid-cols-3 items-center gap-6">
            <TeamCard team={away} score={data.away_score} won={!homeWon} testId="share-away-score" />
            <div className="text-center">
              <div className="text-[10px] font-mono uppercase tracking-widest text-slate-400 mb-1">FINAL</div>
              <Trophy className="mx-auto text-amber-400 mb-1" size={28} />
              <div className="text-[10px] font-mono uppercase tracking-widest text-amber-300">
                {(homeWon ? home?.name : away?.name)?.toUpperCase()} WIN
              </div>
              {data.weather && (
                <div className="mt-2 text-[10px] font-mono uppercase tracking-widest text-slate-500">
                  Weather: {data.weather}
                </div>
              )}
            </div>
            <TeamCard team={home} score={data.home_score} won={homeWon} align="right" testId="share-home-score" />
          </div>
        </div>

        {/* Team stats */}
        {data.stats && (
          <div className="card-broadcast p-5">
            <h3 className="font-display font-extrabold uppercase text-lg tracking-wide mb-3">Team Stats</h3>
            <div className="grid grid-cols-[1fr_auto_1fr] gap-x-6 gap-y-1 text-sm">
              <div className="text-right font-display font-black uppercase" style={{ color: away?.primary }}>{away?.id}</div>
              <div className="text-center text-[10px] font-mono uppercase tracking-widest text-slate-500">STAT</div>
              <div className="text-left font-display font-black uppercase" style={{ color: home?.primary }}>{home?.id}</div>
              {[
                ["Total Yards", "total_yards"],
                ["Passing Yards", "pass_yards"],
                ["Rushing Yards", "rush_yards"],
                ["First Downs", "first_downs"],
                ["Turnovers", "turnovers"],
                ["Sacks Allowed", "sacks_allowed"],
                ["TDs", "tds"],
                ["FGs", "fgs"],
              ].map(([label, key]) => (
                <React.Fragment key={key}>
                  <div className="text-right font-mono text-slate-200 tabular-nums">{data.stats[data.away]?.[key] ?? 0}</div>
                  <div className="text-center text-[11px] font-mono uppercase tracking-widest text-slate-400">{label}</div>
                  <div className="text-left font-mono text-slate-200 tabular-nums">{data.stats[data.home]?.[key] ?? 0}</div>
                </React.Fragment>
              ))}
            </div>
          </div>
        )}

        {/* Top plays */}
        <div className="card-broadcast p-5">
          <h3 className="font-display font-extrabold uppercase text-lg tracking-wide mb-3">Top Plays</h3>
          <div className="space-y-2">
            {data.top_plays.map((p, i) => (
              <div key={i} className="p-3 rounded-lg border border-white/5 bg-[#0A0F1D]/50">
                <div className="flex items-center gap-2 text-[10px] font-mono uppercase tracking-widest text-slate-400 mb-1">
                  <span className="w-1.5 h-3 rounded-sm" style={{ background: teamsById[p.off]?.primary }} />
                  {p.off} • Q{p.quarter} • Roll {p.dice[0]}+{p.dice[1]}={p.roll}
                  <span className={`ml-auto px-1.5 py-0.5 rounded ${p.result === "TD" ? "bg-emerald-500/25 text-emerald-300" : p.result === "INT" || p.result === "FUMBLE" ? "bg-rose-500/25 text-rose-300" : "bg-amber-500/20 text-amber-300"}`}>
                    {p.result}
                  </span>
                </div>
                <div className="text-sm text-slate-200">{p.description}</div>
              </div>
            ))}
            {data.top_plays.length === 0 && (
              <div className="text-center py-5 text-slate-500 font-mono text-xs">No highlight plays</div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}

function TeamCard({ team, score, won, align = "left", testId }) {
  if (!team) return null;
  return (
    <div className={`flex ${align === "right" ? "flex-row-reverse" : ""} items-center gap-3`}>
      <div
        className="w-16 h-16 rounded-lg grid place-items-center font-display font-black text-white text-2xl shrink-0"
        style={{ background: team.primary, borderRight: `4px solid ${team.secondary}` }}
      >
        {team.id}
      </div>
      <div className={`${align === "right" ? "text-right" : ""}`}>
        <div className="text-xs uppercase font-mono tracking-widest text-slate-400">{team.city}</div>
        <div className="font-display font-black text-2xl uppercase leading-none">{team.name}</div>
        <div
          className={`led-text text-5xl leading-none mt-1 ${won ? "" : "opacity-75"}`}
          data-testid={testId}
        >
          {String(score).padStart(2, "0")}
        </div>
      </div>
    </div>
  );
}
