import React, { useEffect, useState } from "react";
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { api, listTeams } from "@/lib/api";
import { toast } from "sonner";
import { Swords } from "lucide-react";

export default function RivalPickerDialog({ seasonId, team, open, onOpenChange, onSaved }) {
  const [teams, setTeams] = useState([]);
  const [rivals, setRivals] = useState(null);
  const [picked, setPicked] = useState(null);

  useEffect(() => {
    if (!open) return;
    listTeams().then(setTeams);
    api.get(`/season/${seasonId}/rivals/${team.id}`).then((r) => {
      setRivals(r.data);
      setPicked(r.data.extra_rival);
    });
  }, [open, team?.id, seasonId]);

  const save = async () => {
    if (!picked) return;
    try {
      await api.post("/season/rival", { season_id: seasonId, team: team.id, rival: picked });
      toast.success(`New rival: ${picked}`);
      onOpenChange(false);
      onSaved?.();
    } catch {
      toast.error("Failed to save rival");
    }
  };

  if (!rivals) return null;
  const eligible = teams.filter((t) => t.id !== team.id && !rivals.division_rivals.includes(t.id));

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-2xl card-broadcast border-white/10 text-slate-100 p-0" data-testid="rival-picker-dialog">
        <div className="px-5 py-4 border-b border-white/10" style={{ background: `linear-gradient(120deg, ${team?.primary} 0%, rgba(13,19,34,0.9) 70%)` }}>
          <DialogHeader>
            <div className="text-[10px] font-mono uppercase tracking-widest text-slate-200/80 flex items-center gap-1.5">
              <Swords size={11} /> Rivalries
            </div>
            <DialogTitle className="font-display font-black uppercase text-2xl">Pick a Rival</DialogTitle>
          </DialogHeader>
        </div>
        <div className="p-5 space-y-4">
          <div>
            <div className="text-[10px] font-mono uppercase tracking-widest text-slate-400 mb-1">Auto Division Rivals</div>
            <div className="flex flex-wrap gap-1.5">
              {rivals.division_rivals.map((r) => (
                <span key={r} className="px-2.5 py-1 rounded font-mono uppercase text-xs text-slate-300 bg-white/5 border border-white/10">{r}</span>
              ))}
            </div>
          </div>
          <div>
            <div className="text-[10px] font-mono uppercase tracking-widest text-amber-400 mb-2">Pick An Extra Rival</div>
            <div className="grid grid-cols-4 sm:grid-cols-6 gap-1.5 max-h-72 overflow-y-auto scrollbar-thin">
              {eligible.map((t) => {
                const active = picked === t.id;
                return (
                  <button
                    key={t.id}
                    onClick={() => setPicked(t.id)}
                    data-testid={`rival-pick-${t.id}`}
                    className={`p-2 rounded font-display font-bold uppercase text-xs tracking-wider transition-colors ${
                      active ? "text-slate-900" : "text-slate-300 hover:bg-white/5"
                    }`}
                    style={active ? { background: t.primary } : { background: `${t.primary}25` }}
                  >
                    {t.id}
                  </button>
                );
              })}
            </div>
          </div>
          <div className="flex justify-end gap-2">
            <button
              onClick={() => onOpenChange(false)}
              className="px-4 py-2 rounded-md bg-white/5 hover:bg-white/10 text-slate-200 font-mono uppercase text-xs tracking-widest"
            >
              Cancel
            </button>
            <button
              onClick={save}
              disabled={!picked}
              data-testid="rival-save-btn"
              className="px-4 py-2 rounded-md bg-amber-500 hover:bg-amber-400 text-slate-900 font-display font-black uppercase text-xs tracking-widest disabled:opacity-40"
            >
              Confirm Rival
            </button>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  );
}
