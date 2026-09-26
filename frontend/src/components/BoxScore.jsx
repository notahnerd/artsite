import React from "react";

function contrastColor(team) {
  // pick the lighter of primary/secondary for readability on dark bg
  const parse = (h) => {
    if (!h) return null;
    const c = h.replace("#", "");
    return [parseInt(c.slice(0, 2), 16), parseInt(c.slice(2, 4), 16), parseInt(c.slice(4, 6), 16)];
  };
  const lum = (rgb) => rgb ? 0.2126 * rgb[0] + 0.7152 * rgb[1] + 0.0722 * rgb[2] : 0;
  const p = parse(team.primary);
  const s = parse(team.secondary);
  const best = lum(p) > lum(s) ? team.primary : team.secondary;
  // if still dark, fall back to a bright team-inspired color
  return lum(parse(best)) > 90 ? best : "#F8FAFC";
}

export default function BoxScore({ stats, home, away }) {
  if (!stats) return null;
  const rows = [
    ["Total Yards", "total_yards"],
    ["Passing Yards", "pass_yards"],
    ["Rushing Yards", "rush_yards"],
    ["First Downs", "first_downs"],
    ["Turnovers", "turnovers"],
    ["Sacks Allowed", "sacks_allowed"],
    ["Touchdowns", "tds"],
    ["Field Goals", "fgs"],
    ["Plays", "plays"],
  ];
  const H = stats[home.id] || {};
  const A = stats[away.id] || {};
  return (
    <div className="card-broadcast p-4" data-testid="box-score-panel">
      <h3 className="font-display font-extrabold uppercase tracking-wide text-lg mb-3">Box Score</h3>
      <div className="grid grid-cols-[1fr_auto_1fr] gap-x-4 gap-y-1 text-sm">
        <div className="text-right font-display font-black text-lg uppercase" style={{ color: contrastColor(away) }}>{away.id}</div>
        <div className="text-center text-[10px] font-mono uppercase tracking-widest text-slate-500">STAT</div>
        <div className="text-left font-display font-black text-lg uppercase" style={{ color: contrastColor(home) }}>{home.id}</div>
        {rows.map(([label, key]) => (
          <React.Fragment key={key}>
            <div className="text-right font-mono text-slate-200 tabular-nums">{A[key] ?? 0}</div>
            <div className="text-center text-[11px] font-mono uppercase tracking-widest text-slate-400">{label}</div>
            <div className="text-left font-mono text-slate-200 tabular-nums">{H[key] ?? 0}</div>
          </React.Fragment>
        ))}
      </div>
    </div>
  );
}
