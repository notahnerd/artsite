import React from "react";

export default function Scoreboard({ state, home, away }) {
  if (!state) return null;
  const homeScore = state[`${home.id}_score`] || 0;
  const awayScore = state[`${away.id}_score`] || 0;
  const clock = fmtClock(state.clock);
  const possessor = state.possession === home.id ? home : away;
  const ballFromOwn = state.ball_on;
  const ballLoc = ballFromOwn > 50 ? `OPP ${100 - ballFromOwn}` : ballFromOwn < 50 ? `OWN ${ballFromOwn}` : "50";

  return (
    <div className="card-broadcast p-4 md:p-6 stadium-glow">
      <div className="grid grid-cols-3 items-center gap-4">
        <TeamPanel team={away} score={awayScore} testId="scoreboard-away-score" align="left" />

        <div className="flex flex-col items-center gap-2">
          <div className="flex items-center gap-2 text-[10px] uppercase font-mono tracking-widest text-slate-400">
            <span className="w-2 h-2 rounded-full bg-red-500 pulse-glow" />
            LIVE • Q{state.quarter}
          </div>
          <div className="led-text text-4xl md:text-5xl" data-testid="scoreboard-clock">{clock}</div>
          <div className="mt-1 flex items-center gap-2 text-xs font-mono text-slate-300" data-testid="scoreboard-down-distance">
            <span className="px-2 py-0.5 rounded bg-amber-500/20 text-amber-300 font-bold">
              {ordinal(state.down)} & {state.distance === 0 ? "GOAL" : state.distance}
            </span>
            <span className="text-slate-400">|</span>
            <span>{ballLoc}</span>
          </div>
          <div className="text-[10px] uppercase font-mono tracking-widest text-slate-500 mt-1">
            <span style={{ color: possessor.primary }}>●</span> {possessor.city} ball
          </div>
        </div>

        <TeamPanel team={home} score={homeScore} testId="scoreboard-home-score" align="right" />
      </div>
    </div>
  );
}

function TeamPanel({ team, score, testId, align }) {
  return (
    <div className={`flex ${align === "right" ? "flex-row-reverse" : ""} items-center gap-3`}>
      <div
        className="w-14 h-14 md:w-16 md:h-16 rounded-lg grid place-items-center font-display font-black text-white text-xl md:text-2xl shrink-0"
        style={{ background: team.primary, borderRight: `4px solid ${team.secondary}` }}
      >
        {team.id}
      </div>
      <div className={`flex flex-col ${align === "right" ? "items-end" : ""}`}>
        <div className="text-xs uppercase tracking-widest font-mono text-slate-400">{team.city}</div>
        <div className="font-display font-black text-xl md:text-2xl uppercase leading-none">{team.name}</div>
        <div className="led-text text-4xl md:text-5xl leading-none mt-1" data-testid={testId}>{String(score).padStart(2, "0")}</div>
      </div>
    </div>
  );
}

function fmtClock(sec) {
  const s = Math.max(0, sec);
  return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, "0")}`;
}

function ordinal(n) {
  return ["1st", "2nd", "3rd", "4th"][n - 1] || `${n}th`;
}
