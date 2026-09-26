import React from "react";
import { Sun, CloudRain, CloudSnow, Wind, Home } from "lucide-react";

const ICONS = {
  CLEAR: Sun,
  RAIN: CloudRain,
  SNOW: CloudSnow,
  WIND: Wind,
  DOME: Home,
};

const LABELS = {
  CLEAR: "Clear",
  RAIN: "Rain",
  SNOW: "Snow",
  WIND: "Windy",
  DOME: "Dome",
};

const COLORS = {
  CLEAR: "#F59E0B",
  RAIN: "#38BDF8",
  SNOW: "#E2E8F0",
  WIND: "#A5F3FC",
  DOME: "#C084FC",
};

export default function WeatherBadge({ code, size = "sm" }) {
  const Icon = ICONS[code] || Sun;
  const label = LABELS[code] || "Clear";
  const color = COLORS[code] || "#F59E0B";
  const px = size === "lg" ? "px-3 py-1.5" : "px-2 py-0.5";
  const text = size === "lg" ? "text-xs" : "text-[10px]";
  return (
    <span
      className={`inline-flex items-center gap-1.5 ${px} rounded font-mono uppercase tracking-widest ${text} border`}
      style={{ background: `${color}20`, borderColor: `${color}40`, color }}
      data-testid={`weather-badge-${code}`}
    >
      <Icon size={size === "lg" ? 14 : 11} />
      {label}
    </span>
  );
}
