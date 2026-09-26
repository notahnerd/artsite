import React from "react";
import { Link, useLocation } from "react-router-dom";

export default function Nav({ seasonId, userTeam }) {
  const loc = useLocation();
  const tabs = seasonId
    ? [
        { path: `/season/${seasonId}`, label: "Season", tid: "nav-season-tab" },
        { path: `/season/${seasonId}/chart`, label: "Player Cards", tid: "nav-chart-tab" },
      ]
    : [];
  return (
    <div className="sticky top-0 z-40 backdrop-blur-xl bg-[#0D1322]/90 border-b border-white/10">
      <div className="max-w-7xl mx-auto px-4 lg:px-8 py-3 flex items-center justify-between">
        <Link to="/" className="flex items-center gap-2.5" data-testid="nav-home">
          <div className="w-9 h-9 rounded-md grid place-items-center bg-amber-500 text-slate-900 font-display font-black text-lg">
            GR
          </div>
          <div>
            <div className="font-display font-black uppercase text-lg leading-none tracking-tight">Gridiron Roller</div>
            <div className="text-[10px] font-mono uppercase tracking-widest text-slate-400">Tabletop NFL Sim '25</div>
          </div>
        </Link>
        <nav className="flex items-center gap-1">
          {tabs.map((t) => {
            const active = loc.pathname === t.path;
            return (
              <Link
                key={t.path}
                to={t.path}
                data-testid={t.tid}
                className={`px-3 py-1.5 rounded-md text-xs font-mono uppercase tracking-widest font-semibold ${
                  active ? "bg-amber-500 text-slate-900" : "text-slate-300 hover:bg-white/5"
                }`}
              >
                {t.label}
              </Link>
            );
          })}
          {userTeam && (
            <div className="ml-2 px-2.5 py-1 rounded-md text-[10px] font-mono uppercase tracking-widest bg-white/5 text-slate-300 border border-white/10">
              MGR: <span className="text-amber-400 font-bold">{userTeam}</span>
            </div>
          )}
        </nav>
      </div>
    </div>
  );
}
