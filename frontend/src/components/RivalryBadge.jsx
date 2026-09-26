import React from "react";
import { Swords } from "lucide-react";

export default function RivalryBadge({ compact = false }) {
  return (
    <span
      className={`inline-flex items-center gap-1 rounded font-mono uppercase tracking-widest font-bold border ${
        compact ? "px-1.5 py-0.5 text-[9px]" : "px-2 py-1 text-[10px]"
      }`}
      style={{ background: "rgba(244,63,94,0.15)", borderColor: "rgba(244,63,94,0.5)", color: "#FDA4AF" }}
      data-testid="rivalry-badge"
    >
      <Swords size={compact ? 9 : 11} /> Rivalry
    </span>
  );
}
