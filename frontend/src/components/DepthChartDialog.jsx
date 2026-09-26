import React, { useEffect, useState } from "react";
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { api } from "@/lib/api";
import { toast } from "sonner";

export default function DepthChartDialog({ seasonId, team, open, onOpenChange, onSaved }) {
  const [data, setData] = useState(null);
  const [depth, setDepth] = useState({});
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    if (open && team?.id) {
      api.get(`/season/${seasonId}/depth-chart/${team.id}`).then((r) => {
        setData(r.data);
        // seed depth from server + starters
        const initial = { ...(r.data.depth || {}) };
        for (const p of r.data.roster) {
          if (p.starter && !initial[p.pos]) initial[p.pos] = p.name;
        }
        setDepth(initial);
      });
    }
  }, [open, team?.id, seasonId]);

  const positions = ["QB", "RB", "K"]; // swappable positions
  const roster = data?.roster || [];

  const pick = (pos, name) => setDepth((d) => ({ ...d, [pos]: name }));

  const save = async () => {
    setSaving(true);
    try {
      await api.post("/season/depth-chart", {
        season_id: seasonId,
        team: team.id,
        depth,
      });
      toast.success(`Depth chart updated for ${team.id}`);
      onOpenChange(false);
      onSaved?.();
    } catch (e) {
      toast.error("Save failed");
    } finally {
      setSaving(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent
        className="max-w-xl card-broadcast border-white/10 text-slate-100 p-0 overflow-hidden"
        data-testid="depth-chart-modal"
      >
        <div
          className="px-5 py-4 border-b border-white/10"
          style={{ background: `linear-gradient(120deg, ${team?.primary} 0%, rgba(13,19,34,0.9) 70%)` }}
        >
          <DialogHeader>
            <div className="text-[10px] font-mono uppercase tracking-widest text-slate-200/70">
              {team?.city} {team?.name}
            </div>
            <DialogTitle className="font-display font-black uppercase text-2xl">Depth Chart</DialogTitle>
          </DialogHeader>
        </div>
        <div className="p-5 space-y-4">
          <p className="text-xs font-mono text-slate-400">
            Choose your starter for each position. Backups have lower ratings and different card outcomes.
          </p>
          {positions.map((pos) => {
            const options = roster.filter((p) => p.pos === pos).sort((a, b) => b.ovr - a.ovr);
            if (!options.length) return null;
            return (
              <div key={pos}>
                <div className="text-[10px] font-mono uppercase tracking-widest text-amber-400 mb-2">{pos}</div>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                  {options.map((p) => {
                    const active = depth[pos] === p.name;
                    return (
                      <button
                        key={p.name}
                        onClick={() => pick(pos, p.name)}
                        data-testid={`depth-pick-${pos}-${p.name.replace(/\s+/g,'-')}`}
                        className={`p-2.5 rounded-lg border text-left flex items-center gap-2 transition-colors ${
                          active ? "border-amber-400 bg-amber-500/10" : "border-white/10 hover:border-white/25"
                        }`}
                      >
                        <div className="text-[10px] font-mono text-slate-400 w-8 tabular-nums">#{p.num}</div>
                        <div className="flex-1">
                          <div className="font-display font-bold uppercase text-sm">{p.name}</div>
                          <div className="text-[10px] font-mono text-slate-500">{p.role || p.pos}</div>
                        </div>
                        <div className={`font-mono font-bold ${p.ovr >= 90 ? "text-emerald-300" : p.ovr >= 82 ? "text-amber-300" : "text-slate-300"}`}>
                          {p.ovr}
                        </div>
                      </button>
                    );
                  })}
                </div>
              </div>
            );
          })}
          <div className="flex justify-end gap-2 pt-2">
            <button
              onClick={() => onOpenChange(false)}
              className="px-4 py-2 rounded-md bg-white/5 hover:bg-white/10 text-slate-200 font-mono uppercase text-xs tracking-widest"
            >
              Cancel
            </button>
            <button
              onClick={save}
              disabled={saving}
              data-testid="depth-chart-save-btn"
              className="px-4 py-2 rounded-md bg-amber-500 hover:bg-amber-400 text-slate-900 font-display font-black uppercase text-xs tracking-widest disabled:opacity-50"
            >
              {saving ? "Saving…" : "Save Depth Chart"}
            </button>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  );
}
