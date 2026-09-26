import React, { useEffect, useState } from "react";
import { useParams, useNavigate } from "react-router-dom";
import { api, getSeason } from "@/lib/api";
import { toast } from "sonner";
import Nav from "@/components/Nav";
import { Trophy, Sparkles, Calendar } from "lucide-react";

export default function Franchise() {
  const { id } = useParams();
  const nav = useNavigate();
  const [data, setData] = useState(null);
  const [season, setSeason] = useState(null);
  const [rolling, setRolling] = useState(false);

  const load = async () => {
    const [f, s] = await Promise.all([
      api.get(`/season/${id}/franchise`).then((r) => r.data),
      getSeason(id),
    ]);
    setData(f);
    setSeason(s);
  };
  useEffect(() => { load(); /* eslint-disable-next-line */ }, [id]);

  const nextYear = async () => {
    setRolling(true);
    try {
      const r = await api.post("/season/next-year", { season_id: id });
      toast.success(`Welcome to ${r.data.year} season!`);
      localStorage.setItem("gr_last_season", JSON.stringify({ id: r.data.new_season_id, team: season.user_team }));
      nav(`/season/${r.data.new_season_id}`);
    } catch (e) {
      toast.error(e?.response?.data?.detail || "Roll failed");
    } finally {
      setRolling(false);
    }
  };

  if (!data || !season) return <div className="min-h-screen grid place-items-center text-slate-400 font-mono">Loading…</div>;

  const playoffsDone = !!season.playoffs?.champion;
  const myRetirements = data.last_year_retirements[season.user_team] || [];
  const myRookies = data.last_year_rookies[season.user_team] || [];

  return (
    <div className="min-h-screen">
      <Nav seasonId={id} userTeam={season.user_team} />
      <div className="max-w-5xl mx-auto px-4 lg:px-8 py-6 space-y-6">
        <div>
          <div className="text-xs font-mono uppercase tracking-[0.3em] text-amber-400">Franchise HQ</div>
          <h1 className="font-display font-black uppercase text-3xl md:text-4xl">{data.year} Franchise • {season.user_team}</h1>
        </div>

        <div className="card-broadcast p-5">
          <div className="flex items-center gap-3 mb-4">
            <Trophy className="text-amber-400" />
            <h3 className="font-display font-extrabold uppercase text-lg tracking-wide">Trophy Case</h3>
          </div>
          {data.champions_history.length === 0 ? (
            <div className="text-slate-500 text-sm font-mono">No championships yet — win a Super Bowl to add banners.</div>
          ) : (
            <div className="grid grid-cols-2 md:grid-cols-4 gap-2">
              {data.champions_history.map((c, i) => (
                <div key={i} className="p-3 rounded-lg border border-amber-400/40 bg-amber-500/5">
                  <div className="text-[10px] font-mono uppercase tracking-widest text-slate-400">Season {c.year}</div>
                  <div className="font-display font-black text-xl uppercase mt-0.5 text-amber-300">{c.team}</div>
                </div>
              ))}
            </div>
          )}
        </div>

        {(myRookies.length > 0 || myRetirements.length > 0) && (
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {myRetirements.length > 0 && (
              <div className="card-broadcast p-5">
                <div className="text-[10px] font-mono uppercase tracking-widest text-slate-400 mb-2">Off the Roster (Retired)</div>
                <div className="space-y-1.5">
                  {myRetirements.map((r) => (
                    <div key={r.name} className="flex items-center gap-2 text-sm">
                      <span className="text-slate-300">{r.name}</span>
                      <span className="text-[10px] font-mono text-slate-500">{r.pos} • Age {r.age}</span>
                      <span className="ml-auto font-mono text-slate-400">{r.ovr} OVR</span>
                    </div>
                  ))}
                </div>
              </div>
            )}
            {myRookies.length > 0 && (
              <div className="card-broadcast p-5">
                <div className="flex items-center gap-2 mb-2">
                  <Sparkles size={14} className="text-emerald-400" />
                  <span className="text-[10px] font-mono uppercase tracking-widest text-emerald-300">Rookie Class</span>
                </div>
                <div className="space-y-1.5">
                  {myRookies.map((r) => (
                    <div key={r.name} className="flex items-center gap-2 text-sm">
                      <span className="text-emerald-200 font-semibold">{r.name}</span>
                      <span className="text-[10px] font-mono text-slate-500">{r.pos} • Age {r.age}</span>
                      <span className="ml-auto font-mono text-amber-300">{r.ovr} OVR</span>
                    </div>
                  ))}
                </div>
              </div>
            )}
          </div>
        )}

        <div className="card-broadcast p-5 flex items-center gap-4">
          <Calendar className="text-amber-400" />
          <div>
            <h3 className="font-display font-extrabold uppercase text-lg tracking-wide">Roll to Next Year</h3>
            <p className="text-slate-400 text-sm">Age all players, generate rookies, retire veterans, and start the {data.year + 1} season.</p>
          </div>
          <button
            onClick={nextYear}
            disabled={rolling || !playoffsDone}
            data-testid="next-year-btn"
            className="ml-auto px-5 py-3 rounded-md bg-amber-500 hover:bg-amber-400 text-slate-900 font-display font-black uppercase tracking-wider disabled:opacity-40"
          >
            {rolling ? "Rolling…" : `Start ${data.year + 1} →`}
          </button>
        </div>
        {!playoffsDone && (
          <div className="text-center text-xs font-mono text-slate-500">
            Finish the current season and win the Super Bowl (or lose it) before rolling forward.
          </div>
        )}
      </div>
    </div>
  );
}
