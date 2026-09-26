import React from "react";

const RESULT_COLORS = {
  TD: "bg-emerald-500/25 text-emerald-300 border-emerald-500/40",
  FG_GOOD: "bg-cyan-500/25 text-cyan-300 border-cyan-500/40",
  FG_MISS: "bg-rose-500/20 text-rose-300 border-rose-500/40",
  INT: "bg-rose-600/30 text-rose-300 border-rose-500/50",
  FUMBLE: "bg-rose-600/30 text-rose-300 border-rose-500/50",
  SACK: "bg-orange-500/25 text-orange-300 border-orange-500/40",
  INCOMPLETE: "bg-slate-600/25 text-slate-300 border-slate-500/40",
  BIG_PLAY: "bg-amber-500/25 text-amber-300 border-amber-500/40",
  BREAKAWAY: "bg-amber-500/25 text-amber-300 border-amber-500/40",
  DEEP_BOMB: "bg-amber-500/25 text-amber-300 border-amber-500/40",
  PUNT: "bg-slate-600/20 text-slate-300 border-slate-500/30",
  NORMAL: "bg-slate-700/20 text-slate-300 border-slate-600/30",
  TFL: "bg-orange-500/20 text-orange-300 border-orange-500/30",
};

export default function PlayByPlay({ plays, teamsById }) {
  return (
    <div className="card-broadcast p-4 flex flex-col" style={{ maxHeight: 480 }}>
      <div className="flex items-center justify-between mb-3">
        <h3 className="font-display font-extrabold uppercase tracking-wide text-lg">Play-by-Play</h3>
        <span className="text-[10px] font-mono uppercase tracking-widest text-slate-400">{plays.length} plays</span>
      </div>
      <div className="flex-1 overflow-y-auto scrollbar-thin space-y-2 pr-1" data-testid="play-by-play-container">
        {plays.slice().reverse().map((p, i) => (
          <PlayCard key={i} play={p} team={teamsById[p.off]} />
        ))}
        {plays.length === 0 && (
          <div className="text-center py-10 text-slate-500 text-sm font-mono uppercase tracking-widest">
            Roll dice to start the game
          </div>
        )}
      </div>
    </div>
  );
}

function PlayCard({ play, team }) {
  const color = RESULT_COLORS[play.result] || RESULT_COLORS.NORMAL;
  return (
    <div className="slide-in p-3 rounded-lg border bg-[#0A0F1D]/60 border-white/5 hover:border-white/15">
      <div className="flex items-center justify-between text-[10px] font-mono uppercase tracking-widest text-slate-400 mb-1.5">
        <span className="flex items-center gap-1.5">
          <span className="w-2 h-2 rounded-sm" style={{ background: team?.primary }} />
          {team?.id} • Q{play.quarter}
        </span>
        <span className={`px-1.5 py-0.5 rounded border font-bold ${color}`}>{play.result}</span>
      </div>
      <div className="text-sm text-slate-200 leading-snug">{play.description}</div>
      <div className="mt-1.5 flex items-center gap-3 text-[10px] font-mono uppercase tracking-widest text-slate-500">
        <span>Roll {play.dice[0]}+{play.dice[1]}={play.roll}</span>
        <span>•</span>
        <span>{play.play_type}</span>
        <span>•</span>
        <span className={play.yards >= 0 ? "text-emerald-400" : "text-rose-400"}>
          {play.yards >= 0 ? "+" : ""}{play.yards} yd
        </span>
      </div>
    </div>
  );
}
