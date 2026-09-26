import React, { useEffect, useState } from "react";
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { api } from "@/lib/api";
import { toast } from "sonner";

export default function TradeDialog({ seasonId, myTeam, open, onOpenChange, onDone }) {
  const [teams, setTeams] = useState([]);
  const [myRoster, setMyRoster] = useState([]);
  const [selectedTeamId, setSelectedTeamId] = useState(null);
  const [myPlayer, setMyPlayer] = useState(null);
  const [theirPlayer, setTheirPlayer] = useState(null);
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    if (!open) return;
    api.get(`/season/${seasonId}/tradeable-players`, { params: { exclude_team: myTeam.id } }).then((r) => {
      setTeams(r.data.teams);
    });
    api.get(`/season/${seasonId}/depth-chart/${myTeam.id}`).then((r) => {
      const roster = (r.data.roster || []).filter(
        (p) => p.starter && ["QB", "RB", "WR", "TE", "K"].includes(p.pos)
      );
      setMyRoster(roster);
    });
    setSelectedTeamId(null);
    setMyPlayer(null);
    setTheirPlayer(null);
  }, [open, seasonId, myTeam?.id]);

  const otherTeam = teams.find((t) => t.team.id === selectedTeamId);
  const targets = otherTeam ? otherTeam.players.filter((p) => !myPlayer || p.pos === myPlayer.pos) : [];

  const submit = async () => {
    if (!myPlayer || !theirPlayer) return;
    setSubmitting(true);
    try {
      await api.post("/season/trade", {
        season_id: seasonId,
        my_team: myTeam.id,
        my_player: myPlayer.name,
        other_team: selectedTeamId,
        other_player: theirPlayer.name,
      });
      toast.success(`Trade complete: ${myPlayer.name} ↔ ${theirPlayer.name}`);
      onOpenChange(false);
      onDone?.();
    } catch (e) {
      toast.error(e?.response?.data?.detail || "Trade failed");
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-3xl card-broadcast border-white/10 text-slate-100 p-0" data-testid="trade-dialog">
        <div className="px-5 py-4 border-b border-white/10" style={{ background: `linear-gradient(120deg, ${myTeam?.primary} 0%, rgba(13,19,34,0.9) 70%)` }}>
          <DialogHeader>
            <div className="text-[10px] font-mono uppercase tracking-widest text-slate-200/80">Week 8 • Trade Deadline</div>
            <DialogTitle className="font-display font-black uppercase text-2xl">Propose a Trade</DialogTitle>
          </DialogHeader>
        </div>
        <div className="p-5 grid grid-cols-1 lg:grid-cols-2 gap-6">
          {/* Left: my player */}
          <div>
            <div className="text-[10px] font-mono uppercase tracking-widest text-amber-400 mb-2">
              You Send • {myTeam?.id}
            </div>
            <div className="space-y-1.5 max-h-80 overflow-y-auto scrollbar-thin pr-1">
              {myRoster.map((p) => {
                const active = myPlayer?.name === p.name;
                return (
                  <button
                    key={p.name}
                    onClick={() => { setMyPlayer(p); setTheirPlayer(null); }}
                    data-testid={`trade-my-${p.name.replace(/\s+/g,'-')}`}
                    className={`w-full p-2.5 rounded-lg border text-left flex items-center gap-2 ${active ? "border-amber-400 bg-amber-500/10" : "border-white/10 hover:border-white/25"}`}
                  >
                    <span className="text-[10px] font-mono text-slate-400 w-6">#{p.num}</span>
                    <span className="font-display font-bold uppercase text-sm flex-1 truncate">{p.name}</span>
                    <span className="text-[10px] font-mono text-slate-400">{p.pos}</span>
                    <span className="font-mono font-bold text-amber-300 w-8 text-right">{p.ovr}</span>
                  </button>
                );
              })}
            </div>
          </div>
          {/* Right: pick team then their player */}
          <div>
            <div className="text-[10px] font-mono uppercase tracking-widest text-amber-400 mb-2">You Receive</div>
            {!selectedTeamId ? (
              <>
                <div className="text-xs font-mono text-slate-400 mb-2">Pick a team</div>
                <div className="grid grid-cols-4 sm:grid-cols-6 gap-1.5 max-h-80 overflow-y-auto scrollbar-thin">
                  {teams.map((t) => (
                    <button
                      key={t.team.id}
                      onClick={() => setSelectedTeamId(t.team.id)}
                      className="p-2 rounded font-display font-bold uppercase text-xs tracking-wider text-slate-300 hover:bg-white/5"
                      style={{ background: `${t.team.primary}30` }}
                    >
                      {t.team.id}
                    </button>
                  ))}
                </div>
              </>
            ) : (
              <>
                <div className="flex items-center gap-2 mb-2">
                  <button
                    onClick={() => { setSelectedTeamId(null); setTheirPlayer(null); }}
                    className="text-xs font-mono uppercase tracking-widest text-slate-400 hover:text-amber-400"
                  >
                    ← Change team
                  </button>
                  <span className="ml-auto text-xs font-mono">{otherTeam?.team.city} {otherTeam?.team.name}</span>
                </div>
                <div className="space-y-1.5 max-h-72 overflow-y-auto scrollbar-thin pr-1">
                  {targets.map((p) => {
                    const active = theirPlayer?.name === p.name;
                    return (
                      <button
                        key={p.name}
                        onClick={() => setTheirPlayer(p)}
                        data-testid={`trade-their-${p.name.replace(/\s+/g,'-')}`}
                        className={`w-full p-2.5 rounded-lg border text-left flex items-center gap-2 ${active ? "border-amber-400 bg-amber-500/10" : "border-white/10 hover:border-white/25"}`}
                      >
                        <span className="text-[10px] font-mono text-slate-400 w-6">#{p.num}</span>
                        <span className="font-display font-bold uppercase text-sm flex-1 truncate">{p.name}</span>
                        <span className="text-[10px] font-mono text-slate-400">{p.pos}</span>
                        <span className="font-mono font-bold text-amber-300 w-8 text-right">{p.ovr}</span>
                      </button>
                    );
                  })}
                  {targets.length === 0 && myPlayer && (
                    <div className="text-center text-slate-500 text-xs font-mono py-6">No matching {myPlayer.pos}s.</div>
                  )}
                </div>
              </>
            )}
          </div>
        </div>
        <div className="p-5 border-t border-white/10 flex items-center justify-between">
          <div className="text-xs font-mono text-slate-300">
            {myPlayer && theirPlayer ? (
              <>
                Trade: <span className="text-amber-300 font-bold">{myPlayer.name}</span> ({myPlayer.ovr}) ↔ <span className="text-amber-300 font-bold">{theirPlayer.name}</span> ({theirPlayer.ovr})
              </>
            ) : (
              <>Pick a starter you'll send and a target player at the same position.</>
            )}
          </div>
          <button
            onClick={submit}
            disabled={!myPlayer || !theirPlayer || submitting}
            data-testid="trade-confirm-btn"
            className="px-4 py-2 rounded-md bg-amber-500 hover:bg-amber-400 text-slate-900 font-display font-black uppercase text-xs tracking-widest disabled:opacity-40"
          >
            {submitting ? "Trading…" : "Confirm Trade"}
          </button>
        </div>
      </DialogContent>
    </Dialog>
  );
}
