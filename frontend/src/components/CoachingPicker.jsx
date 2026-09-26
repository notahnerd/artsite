import React from "react";
import { api } from "@/lib/api";
import { toast } from "sonner";
import { Shield, Zap, Flame } from "lucide-react";

const OPTIONS = [
  { code: "conservative", label: "Conservative", color: "#38BDF8", icon: Shield },
  { code: "balanced", label: "Balanced", color: "#94A3B8", icon: Zap },
  { code: "aggressive", label: "Aggressive", color: "#F43F5E", icon: Flame },
];

export default function CoachingPicker({ seasonId, team, current, onChange }) {
  const set = async (code) => {
    try {
      await api.post("/season/coaching", { season_id: seasonId, team, philosophy: code });
      toast.success(`Coaching: ${OPTIONS.find((o) => o.code === code)?.label}`);
      onChange?.(code);
    } catch {
      toast.error("Failed to set coaching");
    }
  };
  return (
    <div className="flex items-center gap-1 p-1 rounded-lg bg-white/5 border border-white/10" data-testid="coaching-picker">
      {OPTIONS.map((o) => {
        const Icon = o.icon;
        const active = current === o.code;
        return (
          <button
            key={o.code}
            onClick={() => set(o.code)}
            data-testid={`coaching-${o.code}`}
            className="px-2.5 py-1.5 rounded text-[10px] font-mono uppercase tracking-widest font-bold transition-colors flex items-center gap-1"
            style={active ? { background: o.color, color: "#0F172A" } : { color: "#94A3B8" }}
          >
            <Icon size={11} /> {o.label}
          </button>
        );
      })}
    </div>
  );
}
