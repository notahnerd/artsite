import React from "react";

export default function Dice({ values, rolling }) {
  const [d1, d2] = values || [1, 1];
  return (
    <div className="flex items-center gap-3" data-testid="dice-roll-result-display">
      <DieFace value={d1} rolling={rolling} />
      <DieFace value={d2} rolling={rolling} delay />
      <div className="ml-3">
        <div className="text-xs font-mono uppercase tracking-widest text-slate-400">Total</div>
        <div className="led-text text-3xl leading-none">{d1 + d2}</div>
      </div>
    </div>
  );
}

function DieFace({ value, rolling, delay }) {
  const dots = pipsForValue(value);
  return (
    <div
      className={`dice-face w-14 h-14 relative ${rolling ? "dice-rolling" : ""}`}
      style={{ animationDelay: delay ? "0.1s" : "0s" }}
    >
      <div className="absolute inset-0 grid grid-cols-3 grid-rows-3 gap-1 p-2">
        {dots.map((filled, i) => (
          <div key={i} className={`w-2 h-2 rounded-full mx-auto my-auto ${filled ? "bg-slate-900" : ""}`} />
        ))}
      </div>
    </div>
  );
}

function pipsForValue(v) {
  // 9-cell grid mapping
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
