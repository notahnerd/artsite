import React from "react";

export default function Field({ state, home, away }) {
  if (!state) return null;
  const off = state.possession;
  const offTeam = off === home.id ? home : away;
  const defTeam = off === home.id ? away : home;
  // The offense's ball_on is measured from OWN goal (0-100). Flip perspective so that
  // offense is always attacking LEFT->RIGHT: home is right endzone, away is left endzone visually.
  // We display absolute yard position: leftEndzone = away, rightEndzone = home.
  // If off = home, ball position on field = home_ball_on FROM HOME's own goal = leftEndzone->right,
  // so xPct = 100 - ball_on (home starts at right side, moves left toward opponent).
  // Simpler: away drives from left→right; home drives right→left. We'll always show
  // possession attacking left-to-right for consistent animation.
  const ballPct = state.ball_on; // 0 near own goal, 100 near opp end zone
  const firstDownPct = Math.min(100, state.ball_on + state.distance);

  return (
    <div className="card-broadcast p-3">
      <div className="relative turf-bg rounded-lg overflow-hidden" style={{ height: 140 }} data-testid="field-container">
        {/* Endzone left (defense) */}
        <div
          className="absolute inset-y-0 left-0 w-[10%] flex items-center justify-center font-display font-black text-white text-xs md:text-sm uppercase tracking-widest z-10"
          style={{ background: defTeam.primary }}
        >
          <span className="rotate-[-90deg] whitespace-nowrap">{defTeam.name}</span>
        </div>
        {/* Endzone right (offense goal) */}
        <div
          className="absolute inset-y-0 right-0 w-[10%] flex items-center justify-center font-display font-black text-white text-xs md:text-sm uppercase tracking-widest z-10"
          style={{ background: offTeam.primary }}
        >
          <span className="rotate-90 whitespace-nowrap">{offTeam.name}</span>
        </div>

        {/* Yard markers */}
        {[10, 20, 30, 40, 50, 40, 30, 20, 10].map((n, i) => (
          <div
            key={i}
            className="absolute top-1/2 -translate-y-1/2 text-white/70 text-[10px] font-mono font-bold"
            style={{ left: `${10 + (i + 1) * 8}%` }}
          >
            {n}
          </div>
        ))}

        {/* First down line */}
        <div
          className="absolute top-0 bottom-0 w-[2px] bg-yellow-300 shadow-[0_0_8px_rgba(253,224,71,0.9)] z-20"
          style={{ left: `${10 + (firstDownPct / 100) * 80}%` }}
          data-testid="field-first-down-line"
        />
        {/* Line of scrimmage */}
        <div
          className="absolute top-0 bottom-0 w-[2px] bg-blue-400 shadow-[0_0_8px_rgba(96,165,250,0.9)] z-20"
          style={{ left: `${10 + (state.ball_on / 100) * 80}%` }}
          data-testid="field-scrimmage-line"
        />
        {/* Ball marker */}
        <div
          className="absolute top-1/2 -translate-y-1/2 -translate-x-1/2 z-30 transition-all duration-500 ease-out"
          style={{ left: `${10 + (ballPct / 100) * 80}%` }}
          data-testid="field-ball-position"
        >
          <div className="w-4 h-6 rounded-full bg-amber-950 border-2 border-amber-200 shadow-lg pulse-glow" />
        </div>
      </div>
    </div>
  );
}
