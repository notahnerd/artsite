import React, { useEffect, useState } from "react";
import { api } from "@/lib/api";

export default function Leaders({ seasonId, teamsById }) {
  const [data, setData] = useState(null);
  useEffect(() => {
    api.get(`/season/${seasonId}/leaders`).then((r) => setData(r.data));
  }, [seasonId]);

  if (!data) return <div className="text-slate-400 font-mono text-sm">Loading leaders…</div>;

  const boards = [
    { key: "passing", title: "Passing Yards", metric: "pass_yds", secondary: "pass_td", secondaryLabel: "TD" },
    { key: "rushing", title: "Rushing Yards", metric: "rush_yds", secondary: "rush_td", secondaryLabel: "TD" },
    { key: "receiving", title: "Receiving Yards", metric: "rec_yds", secondary: "rec_td", secondaryLabel: "TD" },
    { key: "sacks", title: "Sacks", metric: "sacks", secondary: "picks", secondaryLabel: "INT" },
  ];

  return (
    <div className="grid grid-cols-1 md:grid-cols-2 gap-4" data-testid="leaders-panel">
      {boards.map((b) => (
        <div key={b.key} className="card-broadcast p-4">
          <div className="flex items-center justify-between mb-3">
            <h3 className="font-display font-extrabold uppercase text-lg tracking-wide">{b.title}</h3>
            <span className="text-[10px] font-mono uppercase tracking-widest text-slate-400">Top 10</span>
          </div>
          <div className="space-y-1">
            {data[b.key].slice(0, 10).map((p, i) => (
              <div key={p.name} className="flex items-center gap-2 text-sm border-b border-white/5 pb-1">
                <span className="w-6 text-slate-500 font-mono text-xs tabular-nums">{i + 1}</span>
                <span
                  className="w-1.5 h-4 rounded-sm"
                  style={{ background: teamsById?.[p.team]?.primary || "#334155" }}
                />
                <span className="font-semibold truncate">{p.name}</span>
                <span className="text-[10px] font-mono text-slate-400">{p.team} • {p.pos}</span>
                <span className="ml-auto font-mono font-bold text-amber-300 tabular-nums">{p[b.metric] || 0}</span>
                <span className="text-[10px] font-mono text-slate-500 tabular-nums w-10 text-right">
                  {p[b.secondary] || 0} {b.secondaryLabel}
                </span>
              </div>
            ))}
            {data[b.key].length === 0 && (
              <div className="text-center py-3 text-slate-500 font-mono text-xs uppercase tracking-widest">
                Play some games to populate leaders
              </div>
            )}
          </div>
        </div>
      ))}
    </div>
  );
}
