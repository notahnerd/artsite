import React, { useEffect, useState } from "react";
import { useParams } from "react-router-dom";
import { getSeason, listTeams } from "@/lib/api";
import Nav from "@/components/Nav";
import Leaders from "@/components/Leaders";

export default function LeadersPage() {
  const { id } = useParams();
  const [season, setSeason] = useState(null);
  const [teamsById, setTeamsById] = useState({});

  useEffect(() => {
    getSeason(id).then(setSeason);
    listTeams().then((list) => {
      const map = {}; list.forEach((t) => { map[t.id] = t; });
      setTeamsById(map);
    });
  }, [id]);

  if (!season) return <div className="min-h-screen grid place-items-center text-slate-400 font-mono">Loading…</div>;

  return (
    <div className="min-h-screen">
      <Nav seasonId={id} userTeam={season.user_team} />
      <div className="max-w-7xl mx-auto px-4 lg:px-8 py-6 space-y-6">
        <div>
          <div className="text-xs font-mono uppercase tracking-[0.3em] text-amber-400 mb-1">Season 2025</div>
          <h1 className="font-display font-black uppercase text-3xl md:text-4xl">Stat Leaders</h1>
          <p className="text-slate-300 text-sm mt-2">
            Cumulative player stats across every game played in this season.
          </p>
        </div>
        <Leaders seasonId={id} teamsById={teamsById} />
      </div>
    </div>
  );
}
