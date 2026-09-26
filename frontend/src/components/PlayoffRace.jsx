import React, { useEffect, useState } from "react";
import { api } from "@/lib/api";

export default function PlayoffRace({ seasonId, teamsById }) {
  const [race, setRace] = useState(null);
  useEffect(() => {
    api.get(`/season/${seasonId}/playoff-race`).then((r) => setRace(r.data));
  }, [seasonId]);
  if (!race) return null;

  const badge = (status) => {
    if (status === "IN") return "bg-emerald-500/20 text-emerald-300 border-emerald-500/40";
    if (status === "BUBBLE") return "bg-amber-500/20 text-amber-300 border-amber-500/40";
    return "bg-slate-600/15 text-slate-400 border-slate-600/20";
  };

  return (
    <div className="grid grid-cols-1 md:grid-cols-2 gap-4" data-testid="playoff-race-panel">
      {["AFC", "NFC"].map((conf) => (
        <div key={conf} className="card-broadcast p-4">
          <div className="flex items-center justify-between mb-3">
            <h3 className="font-display font-extrabold uppercase text-lg tracking-wide">
              {conf} Playoff Picture
            </h3>
            <span className="text-[10px] font-mono uppercase tracking-widest text-slate-400">Week {race.current_week}</span>
          </div>
          <div className="space-y-1">
            {race[conf].slice(0, 12).map((t) => (
              <div key={t.team} className="flex items-center gap-2 text-sm py-0.5">
                <span className="w-6 text-slate-500 font-mono text-xs tabular-nums">#{t.seed}</span>
                <span className="w-1.5 h-4 rounded-sm" style={{ background: teamsById?.[t.team]?.primary || "#334155" }} />
                <span className="font-display font-bold uppercase w-10">{t.team}</span>
                <span className="text-slate-400 text-xs font-mono">{t.w}-{t.l}</span>
                <span className={`ml-auto text-[10px] font-mono uppercase tracking-widest px-1.5 py-0.5 rounded border ${badge(t.status)}`}>
                  {t.status === "IN" ? "In" : t.status === "BUBBLE" ? "Bubble" : "Out"}
                </span>
              </div>
            ))}
          </div>
        </div>
      ))}
    </div>
  );
}
