import React, { useEffect, useState } from "react";
import { api } from "@/lib/api";
import { Bandage } from "lucide-react";

/**
 * Rolling ticker showing the most recent injuries across the league.
 * Uses CSS animation for continuous horizontal scroll.
 */
export default function InjuryTicker({ seasonId, teamsById }) {
  const [items, setItems] = useState([]);
  useEffect(() => {
    if (!seasonId) return;
    api.get(`/season/${seasonId}/injuries`).then((r) => {
      const all = [];
      Object.entries(r.data.injuries || {}).forEach(([tid, list]) => {
        list.forEach((i) => all.push({ ...i, team: tid }));
      });
      // sort by weeks remaining (fresh injuries first)
      all.sort((a, b) => a.weeks_remaining - b.weeks_remaining);
      setItems(all.slice(0, 20));
    });
  }, [seasonId]);

  if (items.length === 0) return null;

  // Duplicate for continuous loop
  const looped = [...items, ...items];

  return (
    <div className="relative overflow-hidden card-broadcast py-2" data-testid="injury-ticker">
      <div className="absolute inset-y-0 left-0 z-10 px-3 flex items-center bg-[#0D1322] border-r border-white/10">
        <Bandage size={14} className="text-rose-400 mr-1.5" />
        <span className="text-[10px] font-mono uppercase tracking-[0.25em] text-rose-300">Injury Wire</span>
      </div>
      <div className="flex gap-8 ticker-track pl-40">
        {looped.map((it, i) => {
          const t = teamsById?.[it.team];
          return (
            <div key={i} className="flex items-center gap-2 shrink-0 text-xs font-mono">
              <span
                className="w-1.5 h-4 rounded-sm"
                style={{ background: t?.primary || "#334155" }}
              />
              <span className="font-display font-bold uppercase">{it.team}</span>
              <span className="text-rose-300 font-semibold">{it.player}</span>
              <span className="text-slate-400">({it.pos})</span>
              <span className="text-slate-500 italic">{it.desc}</span>
              <span className="text-amber-300">— {it.weeks_remaining}w out</span>
              <span className="text-slate-600">•</span>
            </div>
          );
        })}
      </div>
      <style>{`
        @keyframes ticker-scroll {
          from { transform: translateX(0); }
          to { transform: translateX(-50%); }
        }
        .ticker-track {
          animation: ticker-scroll 45s linear infinite;
          width: max-content;
        }
      `}</style>
    </div>
  );
}
