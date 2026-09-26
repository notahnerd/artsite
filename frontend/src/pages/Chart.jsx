import React, { useEffect, useState } from "react";
import { useParams } from "react-router-dom";
import { api, getSeason, listTeams } from "@/lib/api";
import Nav from "@/components/Nav";
import PlayerCard from "@/components/PlayerCard";

export default function Chart() {
  const { id } = useParams();
  const [season, setSeason] = useState(null);
  const [teams, setTeams] = useState([]);
  const [selectedTeam, setSelectedTeam] = useState(null);
  const [cards, setCards] = useState([]);

  useEffect(() => {
    getSeason(id).then((s) => { setSeason(s); setSelectedTeam(s.user_team); });
    listTeams().then(setTeams);
  }, [id]);

  useEffect(() => {
    if (!selectedTeam) return;
    api.get(`/team-cards/${selectedTeam}`).then((r) => setCards(r.data.cards));
  }, [selectedTeam]);

  const team = teams.find((t) => t.id === selectedTeam);

  return (
    <div className="min-h-screen">
      <Nav seasonId={id} userTeam={season?.user_team} />
      <div className="max-w-6xl mx-auto px-4 lg:px-8 py-8 space-y-6">
        <div>
          <div className="text-xs font-mono uppercase tracking-[0.3em] text-amber-400 mb-1">Reference</div>
          <h1 className="font-display font-black uppercase text-3xl md:text-4xl">Player Card Vault</h1>
          <p className="text-slate-300 text-sm max-w-3xl mt-2">
            Strat-O-Matic style — every play rolls <span className="text-amber-400 font-semibold">3 white dice (sum 3–18)</span> plus
            <span className="text-rose-400 font-semibold"> 1 red die</span>. Red 1–3 reads the offense card, 4–6 reads the team defense card.
            Star players have bigger, less punishing outcomes on their own cards.
          </p>
        </div>

        {/* Team picker */}
        <div className="card-broadcast p-4">
          <div className="flex items-center gap-2 flex-wrap" data-testid="team-picker">
            {teams.map((t) => (
              <button
                key={t.id}
                onClick={() => setSelectedTeam(t.id)}
                data-testid={`chart-team-${t.id}`}
                className={`px-2.5 py-1.5 rounded font-display font-bold uppercase text-xs tracking-wider transition-colors ${
                  selectedTeam === t.id ? "text-slate-900" : "text-slate-300 hover:bg-white/5"
                }`}
                style={selectedTeam === t.id ? { background: t.primary } : {}}
              >
                {t.id}
              </button>
            ))}
          </div>
        </div>

        {team && (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3" data-testid="team-card-grid">
            {cards.map(({ player, card }) => (
              <PlayerCard key={player.name} team={team} player={player}>
                <button
                  className="card-broadcast text-left p-4 relative overflow-hidden hover:border-amber-500/60 transition-colors"
                  style={{ borderLeft: `4px solid ${team.primary}` }}
                >
                  <div className="flex items-center gap-3">
                    <div
                      className="w-11 h-11 rounded grid place-items-center font-display font-black text-white"
                      style={{ background: team.primary, borderRight: `3px solid ${team.secondary}` }}
                    >
                      #{player.num}
                    </div>
                    <div className="flex-1 min-w-0">
                      <div className="font-display font-black uppercase text-base leading-tight truncate">{player.name}</div>
                      <div className="text-[10px] font-mono uppercase tracking-widest text-slate-400">
                        {player.pos} • {card.type}
                      </div>
                    </div>
                    <div className={`font-mono font-black text-2xl ${player.ovr >= 92 ? "text-emerald-300" : player.ovr >= 85 ? "text-amber-300" : "text-slate-200"}`}>
                      {player.ovr}
                    </div>
                  </div>
                  <MiniChart card={card} />
                </button>
              </PlayerCard>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}

function MiniChart({ card }) {
  const rolls = [3, 6, 8, 10, 11, 13, 15, 18];
  const isYac = card.type === "WR" || card.type === "TE";
  const isDef = card.type === "DEF";
  const values = rolls.map((r) => {
    const c = card.chart[r] || card.chart[String(r)];
    if (!c) return 0;
    if (isYac) return c.yac || 0;
    if (isDef) return c.impact || 0;
    return c.yards ?? 0;
  });
  const max = Math.max(1, ...values.map(Math.abs));
  return (
    <div className="mt-3 flex items-end gap-1 h-10">
      {values.map((v, i) => {
        const h = (Math.abs(v) / max) * 100;
        const positive = v >= 0;
        return (
          <div key={i} className="flex-1 flex flex-col items-center gap-0.5">
            <div
              className={`w-full rounded-sm ${positive ? "bg-emerald-500/60" : "bg-rose-500/60"}`}
              style={{ height: `${Math.max(4, h)}%` }}
              title={`Roll ${rolls[i]}: ${v}`}
            />
            <span className="text-[8px] font-mono text-slate-500">{rolls[i]}</span>
          </div>
        );
      })}
    </div>
  );
}
