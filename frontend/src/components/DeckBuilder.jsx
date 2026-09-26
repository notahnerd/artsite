import React, { useEffect, useState } from "react";
import { api } from "@/lib/api";
import { toast } from "sonner";
import { Layers, Lock, Sparkles, Plus, Trash2 } from "lucide-react";

const PRESET_INFO = {
  balanced:  { label: "Balanced",  desc: "Realistic curve. Mostly 0–8 yd cards, rare breakaways." },
  power_run: { label: "Power Run", desc: "Grind-it-out. More 1–5 yd runs, fewer big plays." },
  air_raid:  { label: "Air Raid",  desc: "Chunk plays and losses. Boom or bust downfield attack." },
  chaos:     { label: "Chaos",     desc: "Wild swings — losses of 10, gains of 80. For the brave." },
};

export default function DeckBuilder({ seasonId, open, onClose }) {
  const [data, setData] = useState(null);
  const [preset, setPreset] = useState("balanced");
  const [sigs, setSigs] = useState([]);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    if (!open || !seasonId) return;
    (async () => {
      try {
        const r = await api.get(`/season/${seasonId}/deck-config`);
        setData(r.data);
        setPreset(r.data.config?.preset || "balanced");
        setSigs(r.data.config?.signature_cards || []);
      } catch (e) {
        toast.error("Failed to load deck config");
      }
    })();
  }, [open, seasonId]);

  if (!open) return null;

  const addSig = () => {
    if (sigs.length >= (data?.max_signature_cards || 3)) return;
    setSigs([...sigs, { label: "New Card", yards: 10, count: 1 }]);
  };

  const updateSig = (idx, patch) => {
    setSigs(sigs.map((s, i) => (i === idx ? { ...s, ...patch } : s)));
  };

  const removeSig = (idx) => setSigs(sigs.filter((_, i) => i !== idx));

  const save = async () => {
    setSaving(true);
    try {
      await api.post("/season/deck-config", {
        season_id: seasonId,
        preset,
        signature_cards: sigs,
      });
      toast.success("Custom deck saved!");
      onClose?.();
    } catch (e) {
      toast.error(e?.response?.data?.detail || "Save failed");
    } finally {
      setSaving(false);
    }
  };

  const unlocked = data?.unlocked;
  const played = data?.seasons_played ?? 0;

  return (
    <div
      className="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4"
      onClick={onClose}
      data-testid="deck-builder-overlay"
    >
      <div
        className="card-broadcast max-w-3xl w-full max-h-[90vh] overflow-y-auto scrollbar-thin p-6"
        onClick={(e) => e.stopPropagation()}
        data-testid="deck-builder-dialog"
      >
        <div className="flex items-center justify-between mb-4">
          <div className="flex items-center gap-2">
            <Layers size={18} className="text-fuchsia-300" />
            <h2 className="text-lg font-display font-black tracking-tight uppercase">
              Custom Flippy Deck
            </h2>
          </div>
          <button
            onClick={onClose}
            className="text-slate-400 hover:text-slate-100 text-xs font-mono uppercase tracking-widest"
            data-testid="deck-builder-close"
          >
            Close
          </button>
        </div>

        {!data ? (
          <div className="text-slate-400 text-sm font-mono">Loading…</div>
        ) : !unlocked ? (
          <div className="text-center py-10" data-testid="deck-builder-locked">
            <Lock size={38} className="text-fuchsia-300 mx-auto mb-3" />
            <div className="text-lg font-display font-black uppercase tracking-tight mb-1">
              Locked
            </div>
            <div className="text-sm text-slate-400 max-w-md mx-auto leading-relaxed">
              Custom Flippy Deck unlocks after your franchise completes{" "}
              <span className="text-amber-300 font-semibold">2 full seasons</span>.
              You've played <span className="text-emerald-300 font-semibold">{played}</span>.
              Win a Super Bowl and come back.
            </div>
          </div>
        ) : (
          <>
            {/* Preset picker */}
            <div className="mb-6">
              <div className="text-[10px] font-mono uppercase tracking-widest text-slate-400 mb-2">
                Yardage Skew
              </div>
              <div className="grid grid-cols-2 md:grid-cols-4 gap-2">
                {Object.entries(PRESET_INFO).map(([key, info]) => (
                  <button
                    key={key}
                    data-testid={`preset-${key}`}
                    onClick={() => setPreset(key)}
                    className={`text-left p-3 rounded-md border transition-colors ${
                      preset === key
                        ? "bg-fuchsia-500/20 border-fuchsia-400 text-fuchsia-100"
                        : "bg-white/5 border-white/10 text-slate-300 hover:bg-white/10"
                    }`}
                  >
                    <div className="text-sm font-display font-bold uppercase tracking-wider">
                      {info.label}
                    </div>
                    <div className="text-[10px] font-mono text-slate-400 mt-1 leading-snug">
                      {info.desc}
                    </div>
                  </button>
                ))}
              </div>
            </div>

            {/* Signature cards */}
            <div className="mb-4">
              <div className="flex items-center justify-between mb-2">
                <div className="flex items-center gap-2">
                  <Sparkles size={14} className="text-amber-300" />
                  <span className="text-[10px] font-mono uppercase tracking-widest text-slate-400">
                    Signature Cards
                  </span>
                </div>
                <button
                  onClick={addSig}
                  disabled={sigs.length >= (data.max_signature_cards || 3)}
                  data-testid="add-signature-card-btn"
                  className="text-[10px] font-mono uppercase tracking-widest px-2 py-1 rounded bg-white/5 border border-white/10 text-slate-300 hover:bg-white/10 disabled:opacity-40 flex items-center gap-1"
                >
                  <Plus size={12} /> Add ({sigs.length}/{data.max_signature_cards || 3})
                </button>
              </div>

              {sigs.length === 0 ? (
                <div className="text-xs text-slate-500 font-mono italic p-3 border border-dashed border-white/10 rounded">
                  Add up to 3 custom cards to your deck. Each replaces a "roll dice" card.
                </div>
              ) : (
                <div className="space-y-2">
                  {sigs.map((s, i) => (
                    <SignatureRow
                      key={i}
                      idx={i}
                      card={s}
                      onChange={(patch) => updateSig(i, patch)}
                      onRemove={() => removeSig(i)}
                      maxCopies={data.max_copies_per_card || 3}
                    />
                  ))}
                </div>
              )}
            </div>

            {/* Save */}
            <div className="flex items-center justify-end gap-2 mt-6 pt-4 border-t border-white/5">
              <button
                onClick={onClose}
                className="px-4 py-2 rounded-md bg-white/5 hover:bg-white/10 text-slate-200 font-mono uppercase tracking-widest text-xs border border-white/10"
              >
                Cancel
              </button>
              <button
                onClick={save}
                disabled={saving}
                data-testid="save-deck-config-btn"
                className="px-5 py-2 rounded-md bg-fuchsia-500 hover:bg-fuchsia-400 text-slate-900 font-display font-black uppercase tracking-wider text-xs disabled:opacity-40"
              >
                {saving ? "Saving…" : "Save Deck"}
              </button>
            </div>
          </>
        )}
      </div>
    </div>
  );
}

function SignatureRow({ idx, card, onChange, onRemove, maxCopies }) {
  return (
    <div
      className="grid grid-cols-12 gap-2 items-center bg-white/5 border border-white/10 rounded p-2"
      data-testid={`signature-row-${idx}`}
    >
      <input
        className="col-span-5 bg-slate-900 border border-white/10 rounded px-2 py-1.5 text-sm text-slate-100 font-mono focus:outline-none focus:border-fuchsia-400"
        value={card.label}
        maxLength={24}
        onChange={(e) => onChange({ label: e.target.value })}
        placeholder="Card name"
        data-testid={`sig-label-${idx}`}
      />
      <div className="col-span-3">
        <label className="text-[9px] font-mono uppercase tracking-widest text-slate-500 block mb-0.5">
          Yards
        </label>
        <input
          type="number"
          min={-10}
          max={60}
          className="w-full bg-slate-900 border border-white/10 rounded px-2 py-1 text-sm text-emerald-300 font-mono tabular-nums text-center focus:outline-none focus:border-fuchsia-400"
          value={card.yards}
          onChange={(e) => onChange({ yards: parseInt(e.target.value || "0", 10) })}
          data-testid={`sig-yards-${idx}`}
        />
      </div>
      <div className="col-span-3">
        <label className="text-[9px] font-mono uppercase tracking-widest text-slate-500 block mb-0.5">
          Copies
        </label>
        <input
          type="number"
          min={1}
          max={maxCopies}
          className="w-full bg-slate-900 border border-white/10 rounded px-2 py-1 text-sm text-amber-300 font-mono tabular-nums text-center focus:outline-none focus:border-fuchsia-400"
          value={card.count}
          onChange={(e) => onChange({ count: parseInt(e.target.value || "1", 10) })}
          data-testid={`sig-count-${idx}`}
        />
      </div>
      <button
        onClick={onRemove}
        className="col-span-1 text-rose-300 hover:text-rose-200 flex justify-center"
        data-testid={`sig-remove-${idx}`}
        aria-label="Remove card"
      >
        <Trash2 size={14} />
      </button>
    </div>
  );
}
