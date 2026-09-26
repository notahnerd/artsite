import React, { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import { listTeams, createSeason, api } from "@/lib/api";
import { toast } from "sonner";

export default function Home() {
  const [teams, setTeams] = useState([]);
  const [selected, setSelected] = useState(null);
  const [difficulty, setDifficulty] = useState("balanced");
  const [creating, setCreating] = useState(false);
  const nav = useNavigate();

  useEffect(() => {
    listTeams().then(setTeams).catch(() => toast.error("Failed to load teams"));
    // Restore last season
    const last = localStorage.getItem("gr_last_season");
    if (last) {
      const { id, team } = JSON.parse(last);
      // just show a toast; user chooses via UI
      toast.info(`Continue Season with ${team}?`, {
        action: { label: "Resume", onClick: () => nav(`/season/${id}`) },
        duration: 8000,
      });
    }
  }, [nav]);

  const start = async () => {
    if (!selected) return;
    setCreating(true);
    try {
      const s = await api.post("/season/create", { user_team: selected, year: 2025, difficulty }).then((r) => r.data);
      localStorage.setItem("gr_last_season", JSON.stringify({ id: s.id, team: selected }));
      toast.success(`Season started as ${selected} (${difficulty})`);
      nav(`/season/${s.id}`);
    } catch (e) {
      toast.error("Could not start season");
    } finally {
      setCreating(false);
    }
  };

  const byConf = teams.reduce((acc, t) => {
    const k = `${t.conf} ${t.div}`;
    (acc[k] = acc[k] || []).push(t);
    return acc;
  }, {});

  return (
    <div className="min-h-screen stadium-glow">
      <div className="max-w-7xl mx-auto px-4 lg:px-8 py-10 md:py-14">
        <div className="flex items-center gap-3 mb-4">
          <div className="w-2 h-8 bg-amber-500" />
          <span className="text-xs font-mono uppercase tracking-[0.35em] text-amber-400">
            SEASON 2025 • WEEK 0 • KICKOFF
          </span>
        </div>
        <h1 className="font-display font-black uppercase tracking-tight text-4xl sm:text-5xl lg:text-6xl leading-[0.95]">
          Pick your <span className="text-amber-400">franchise.</span>
          <br />
          Roll for glory.
        </h1>
        <p className="mt-4 max-w-2xl text-slate-300 text-base leading-relaxed">
          Manage a full 18-week NFL season. Every play crosses two dice with rating charts —
          just like the classic tabletop games, with live 2025 rosters.
        </p>

        <div className="mt-10 space-y-8">
          {Object.entries(byConf).map(([label, list]) => (
            <div key={label}>
              <div className="flex items-center gap-3 mb-3">
                <div className="text-[11px] font-mono font-bold uppercase tracking-[0.3em] text-slate-400">{label}</div>
                <div className="flex-1 h-px bg-white/10" />
              </div>
              <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                {list.map((t) => (
                  <TeamCard
                    key={t.id}
                    team={t}
                    active={selected === t.id}
                    onClick={() => setSelected(t.id)}
                  />
                ))}
              </div>
            </div>
          ))}
        </div>

        <div className="mt-10 sticky bottom-4 flex justify-end items-center gap-3 flex-wrap">
          <div className="flex items-center gap-1 p-1 rounded-lg bg-slate-900/80 border border-white/10 backdrop-blur">
            <span className="px-2 text-[10px] font-mono uppercase tracking-widest text-slate-400">Difficulty</span>
            {["arcade", "balanced", "realistic"].map((d) => (
              <button
                key={d}
                onClick={() => setDifficulty(d)}
                data-testid={`home-difficulty-${d}`}
                className={`px-3 py-1.5 rounded text-[10px] font-mono uppercase tracking-widest font-bold transition-colors ${
                  difficulty === d ? "bg-amber-500 text-slate-900" : "text-slate-300 hover:bg-white/5"
                }`}
              >
                {d}
              </button>
            ))}
          </div>
          <button
            data-testid="start-season-btn"
            disabled={!selected || creating}
            onClick={start}
            className="px-6 py-3 rounded-lg bg-amber-500 hover:bg-amber-400 text-slate-900 font-display font-black uppercase tracking-wider disabled:opacity-40 disabled:cursor-not-allowed shadow-[0_10px_30px_-5px_rgba(245,158,11,0.5)] transition-colors"
          >
            {creating ? "Starting…" : selected ? `Start Season → ${selected}` : "Select a team"}
          </button>
        </div>
      </div>
    </div>
  );
}

function TeamCard({ team, active, onClick }) {
  return (
    <button
      onClick={onClick}
      data-testid={`team-select-card-${team.id}`}
      className={`card-broadcast text-left p-4 flex flex-col gap-1 relative overflow-hidden ${
        active ? "ring-2 ring-amber-500 border-amber-500/60" : ""
      }`}
      style={{ borderLeft: `4px solid ${team.primary}` }}
    >
      <div
        className="absolute -right-6 -top-6 w-24 h-24 rounded-full opacity-25"
        style={{ background: team.primary }}
      />
      <div className="flex items-center gap-2 relative z-10">
        <div
          className="w-9 h-9 rounded grid place-items-center font-display font-black text-white text-sm"
          style={{ background: team.primary, borderRight: `3px solid ${team.secondary}` }}
        >
          {team.id}
        </div>
        <div>
          <div className="text-[10px] font-mono uppercase tracking-widest text-slate-400 leading-none">{team.city}</div>
          <div className="font-display font-black uppercase text-base leading-tight">{team.name}</div>
        </div>
      </div>
      <div className="mt-2 flex items-center gap-3 text-[10px] font-mono uppercase tracking-widest relative z-10">
        <RatingChip label="OFF" v={team.off} />
        <RatingChip label="DEF" v={team.def} />
        <RatingChip label="ST" v={team.st} />
      </div>
    </button>
  );
}

function RatingChip({ label, v }) {
  const c = v >= 90 ? "text-emerald-300" : v >= 82 ? "text-amber-300" : v >= 75 ? "text-slate-200" : "text-slate-400";
  return (
    <span className="flex items-center gap-1">
      <span className="text-slate-500">{label}</span>
      <span className={`${c} font-bold`}>{v}</span>
    </span>
  );
}
