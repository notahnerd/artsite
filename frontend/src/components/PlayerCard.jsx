import React from "react";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/components/ui/dialog";
import { api } from "@/lib/api";

export default function PlayerCard({ team, player, children }) {
  const [data, setData] = React.useState(null);
  const [open, setOpen] = React.useState(false);

  React.useEffect(() => {
    if (open && !data) {
      api
        .get("/player-card", { params: { team: team.id, name: player.name } })
        .then((r) => setData(r.data))
        .catch(() => {});
    }
  }, [open, data, team.id, player.name]);

  const chart = data?.card?.chart;
  const type = data?.card?.type;

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>{children}</DialogTrigger>
      <DialogContent
        className="max-w-lg card-broadcast border-white/10 text-slate-100 p-0 overflow-hidden"
        data-testid="player-card-modal"
      >
        <div
          className="px-5 py-4 border-b border-white/10 relative overflow-hidden"
          style={{ background: `linear-gradient(120deg, ${team.primary} 0%, rgba(13,19,34,0.9) 70%)` }}
        >
          <DialogHeader>
            <div className="flex items-center gap-3">
              <div
                className="w-12 h-12 rounded grid place-items-center font-display font-black text-white"
                style={{ background: team.secondary || team.primary }}
              >
                #{player.num}
              </div>
              <div>
                <div className="text-[10px] font-mono uppercase tracking-widest text-slate-200/80">
                  {team.city} {team.name} • {player.pos}
                </div>
                <DialogTitle className="font-display font-black uppercase text-2xl">
                  {player.name}
                </DialogTitle>
              </div>
              <div className="ml-auto text-right">
                <div className="text-[10px] font-mono uppercase tracking-widest text-slate-300/70">OVR</div>
                <div className="font-mono font-black text-3xl text-amber-300 leading-none">{player.ovr}</div>
              </div>
            </div>
          </DialogHeader>
        </div>
        <div className="p-5">
          {!chart && <div className="text-center text-slate-400 font-mono text-sm">Loading card…</div>}
          {chart && (
            <>
              <div className="text-[10px] font-mono uppercase tracking-widest text-slate-400 mb-2">
                Player Card • {type} • Read row by 3D6 roll (3–18)
              </div>
              <CardTable chart={chart} type={type} />
            </>
          )}
        </div>
      </DialogContent>
    </Dialog>
  );
}

function CardTable({ chart, type }) {
  const rolls = Array.from({ length: 16 }, (_, i) => i + 3);
  const isYacCard = type === "WR" || type === "TE";
  const isDef = type === "DEF";
  const isKicker = type === "K";
  return (
    <table className="w-full text-sm" data-testid="player-card-table">
      <thead>
        <tr className="text-[10px] font-mono uppercase tracking-widest text-slate-400 text-left border-b border-white/5">
          <th className="py-1.5">3D6</th>
          {isYacCard ? (
            <>
              <th>YAC</th>
              <th>Note</th>
            </>
          ) : isDef ? (
            <th>Impact</th>
          ) : isKicker ? (
            <>
              <th>Kick</th>
              <th>Range +/−</th>
            </>
          ) : (
            <>
              <th>Yards</th>
              <th>Result</th>
            </>
          )}
        </tr>
      </thead>
      <tbody>
        {rolls.map((r) => {
          const cell = chart[r] || chart[String(r)];
          if (!cell) return null;
          const y = cell.yards;
          return (
            <tr key={r} className="border-b border-white/5">
              <td className="py-1 font-mono font-bold text-amber-300 tabular-nums w-10">{r}</td>
              {isYacCard ? (
                <>
                  <td className="font-mono tabular-nums text-emerald-300">+{cell.yac}</td>
                  <td className="text-xs text-slate-400 font-mono">{cell.drop ? "DROP" : ""}</td>
                </>
              ) : isDef ? (
                <td className="font-mono tabular-nums text-amber-300">+{cell.impact}</td>
              ) : isKicker ? (
                <>
                  <td className={`text-[10px] font-mono uppercase tracking-widest ${cell.make === false ? "text-rose-300" : cell.result === "BOOMSTICK" || cell.result === "BOMB" ? "text-emerald-300" : "text-slate-200"}`}>
                    {cell.result.replace(/_/g, " ")}
                  </td>
                  <td className="font-mono tabular-nums text-slate-300">
                    {cell.make === false ? "AUTO MISS" : cell.make === true ? "AUTO GOOD" : (cell.range_bonus >= 0 ? "+" : "") + cell.range_bonus + " yd"}
                  </td>
                </>
              ) : (
                <>
                  <td className="font-mono tabular-nums">
                    <span className={y >= 0 ? "text-emerald-300" : "text-rose-300"}>
                      {y >= 0 ? "+" : ""}{y}
                    </span>
                  </td>
                  <td className="text-[10px] font-mono uppercase tracking-widest text-slate-300">
                    {cell.event}
                  </td>
                </>
              )}
            </tr>
          );
        })}
      </tbody>
    </table>
  );
}
