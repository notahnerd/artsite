import React from "react";

export default function Dice({ white, red, read, rolling }) {
  const dice = white || [1, 1, 1];
  const total = dice.reduce((a, b) => a + b, 0);
  const readOff = read === "OFF";
  return (
    <div className="flex items-center gap-4" data-testid="dice-roll-result-display">
      <div className="flex items-center gap-2">
        {dice.map((v, i) => (
          <DieFace key={i} value={v} color="white" rolling={rolling} delay={i * 0.08} />
        ))}
      </div>
      <div className="text-slate-500 font-mono text-xs">+</div>
      <DieFace value={red || 1} color="red" rolling={rolling} delay={0.32} />
      <div className="ml-2">
        <div className="text-[10px] font-mono uppercase tracking-widest text-slate-400">Chart Total</div>
        <div className="led-text text-3xl leading-none">{total}</div>
        <div
          className="mt-1 text-[10px] font-mono uppercase tracking-widest font-bold"
          style={{ color: readOff ? "#F59E0B" : "#F43F5E" }}
          data-testid="dice-read-indicator"
        >
          Red {red} → Read {read || "?"}
        </div>
      </div>
    </div>
  );
}

function DieFace({ value, color, rolling, delay }) {
  const dots = pipsForValue(value);
  const isRed = color === "red";
  const bg = isRed
    ? "linear-gradient(140deg, #EF4444 0%, #B91C1C 100%)"
    : "linear-gradient(140deg, #FFFFFF 0%, #E2E8F0 100%)";
  const dotColor = isRed ? "bg-white" : "bg-slate-900";
  return (
    <div
      className={`w-11 h-11 relative rounded-lg ${rolling ? "dice-rolling" : ""}`}
      style={{
        background: bg,
        animationDelay: `${delay}s`,
        boxShadow: isRed
          ? "inset 0 2px 4px rgba(255,255,255,0.4), inset 0 -2px 4px rgba(0,0,0,0.35), 0 8px 16px rgba(239,68,68,0.4)"
          : "inset 0 2px 4px rgba(255,255,255,0.9), inset 0 -2px 4px rgba(0,0,0,0.15), 0 8px 16px rgba(0,0,0,0.5)",
      }}
    >
      <div className="absolute inset-0 grid grid-cols-3 grid-rows-3 gap-0.5 p-1.5">
        {dots.map((filled, i) => (
          <div key={i} className={`w-1.5 h-1.5 rounded-full mx-auto my-auto ${filled ? dotColor : ""}`} />
        ))}
      </div>
    </div>
  );
}

function pipsForValue(v) {
  const layouts = {
    1: [0,0,0, 0,1,0, 0,0,0],
    2: [1,0,0, 0,0,0, 0,0,1],
    3: [1,0,0, 0,1,0, 0,0,1],
    4: [1,0,1, 0,0,0, 1,0,1],
    5: [1,0,1, 0,1,0, 1,0,1],
    6: [1,0,1, 1,0,1, 1,0,1],
  };
  return layouts[v] || layouts[1];
}
