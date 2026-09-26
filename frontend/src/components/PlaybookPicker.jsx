import React from "react";
import { api } from "@/lib/api";
import { toast } from "sonner";

const STOPS = [
  { code: -0.2, label: "Run Heavy", color: "#84CC16" },
  { code: -0.1, label: "Run Lean", color: "#A3E635" },
  { code: 0.0, label: "Balanced", color: "#94A3B8" },
  { code: 0.1, label: "Pass Lean", color: "#38BDF8" },
  { code: 0.2, label: "Pass Heavy", color: "#818CF8" },
];

export default function PlaybookPicker({ seasonId, team, current, onChange }) {
  const set = async (bias) => {
    try {
      await api.post("/season/playbook", { season_id: seasonId, team, pass_bias: bias });
      const label = STOPS.find((s) => Math.abs(s.code - bias) < 0.001)?.label;
      toast.success(`Playbook: ${label}`);
      onChange?.(bias);
    } catch {
      toast.error("Failed to update playbook");
    }
  };
  const cur = typeof current === "number" ? current : 0;
  return (
    <div className="flex items-center gap-1 p-1 rounded-lg bg-white/5 border border-white/10" data-testid="playbook-picker">
      <span className="px-2 text-[10px] font-mono uppercase tracking-widest text-slate-400">PLAY</span>
      {STOPS.map((s) => {
        const active = Math.abs(s.code - cur) < 0.001;
        return (
          <button
            key={s.code}
            onClick={() => set(s.code)}
            data-testid={`playbook-${s.code}`}
            className="px-2 py-1.5 rounded text-[10px] font-mono uppercase tracking-widest font-bold transition-colors"
            style={active ? { background: s.color, color: "#0F172A" } : { color: "#94A3B8" }}
          >
            {s.label}
          </button>
        );
      })}
    </div>
  );
}
