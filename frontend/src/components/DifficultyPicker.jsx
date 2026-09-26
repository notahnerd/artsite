import React from "react";
import { api } from "@/lib/api";
import { toast } from "sonner";

const OPTIONS = [
  { code: "arcade", label: "Arcade", color: "#F59E0B" },
  { code: "balanced", label: "Balanced", color: "#06B6D4" },
  { code: "realistic", label: "Realistic", color: "#10B981" },
];

export default function DifficultyPicker({ seasonId, current, onChange }) {
  const set = async (code) => {
    try {
      await api.post("/season/difficulty", { season_id: seasonId, difficulty: code });
      toast.success(`Difficulty: ${OPTIONS.find((o) => o.code === code)?.label}`);
      onChange?.(code);
    } catch {
      toast.error("Failed to set difficulty");
    }
  };
  return (
    <div className="flex items-center gap-1 p-1 rounded-lg bg-white/5 border border-white/10" data-testid="difficulty-picker">
      {OPTIONS.map((o) => {
        const active = current === o.code;
        return (
          <button
            key={o.code}
            onClick={() => set(o.code)}
            data-testid={`difficulty-${o.code}`}
            className="px-3 py-1.5 rounded text-[10px] font-mono uppercase tracking-widest font-bold transition-colors"
            style={active ? { background: o.color, color: "#0F172A" } : { color: "#94A3B8" }}
          >
            {o.label}
          </button>
        );
      })}
    </div>
  );
}
