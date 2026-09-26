import React, { useEffect, useState } from "react";
import { api } from "@/lib/api";
import { Bandage } from "lucide-react";

export default function InjuryReport({ seasonId, teamsById }) {
  const [data, setData] = useState(null);
  useEffect(() => {
    api.get(`/season/${seasonId}/injuries`).then((r) => setData(r.data));
  }, [seasonId]);
  if (!data) return null;

  const teams = Object.entries(data.injuries);
  if (teams.length === 0) {
    return (
      <div className="card-broadcast p-4 text-center text-slate-500 font-mono text-xs uppercase tracking-widest">
        <Bandage className="inline mr-2" size={14} /> No active injuries — everyone healthy
      </div>
    );
  }
  return (
    <div className="card-broadcast p-4" data-testid="injury-report">
      <div className="flex items-center justify-between mb-3">
        <h3 className="font-display font-extrabold uppercase text-lg tracking-wide flex items-center gap-2">
          <Bandage size={16} className="text-rose-400" /> Injury Report
        </h3>
        <span className="text-[10px] font-mono uppercase tracking-widest text-slate-400">Week {data.week}</span>
      </div>
      <div className="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-1.5">
        {teams.map(([tid, injs]) => {
          const t = teamsById?.[tid];
          return (
            <div key={tid} className="text-sm">
              <div className="flex items-center gap-2 mb-1">
                <span className="w-1.5 h-3 rounded-sm" style={{ background: t?.primary || "#334155" }} />
                <span className="font-display font-bold uppercase text-xs">{tid}</span>
              </div>
              <div className="pl-3.5 space-y-0.5">
                {injs.map((i, k) => (
                  <div key={k} className="flex items-center gap-2 text-xs">
                    <span className="text-rose-300 font-semibold truncate">{i.player}</span>
                    <span className="text-slate-500 font-mono">{i.pos}</span>
                    <span className="text-slate-400 italic truncate">{i.desc}</span>
                    <span className="ml-auto text-amber-300 font-mono">{i.weeks_remaining}w</span>
                  </div>
                ))}
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
}
