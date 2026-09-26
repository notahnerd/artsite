import React, { useEffect, useRef, useState } from "react";
import { Layers, Zap, AlertTriangle } from "lucide-react";

/**
 * FlippyDeck – shows a card flip for every RUN/PASS play.
 * Face-down while a play resolves; face-up shortly after landing.
 * `card` is the play.card object from the backend:
 *   { type: "DICE" | "YARDS" | "INJURY", yards?: number, injury?: {...}, remaining: number }
 * `flipping` flips the card face-down while true, then back up.
 */
export default function FlippyDeck({ card, flipping }) {
  const [showFace, setShowFace] = useState(false);
  const timerRef = useRef(null);

  useEffect(() => {
    clearTimeout(timerRef.current);
    if (!card) {
      setShowFace(false);
      return;
    }
    if (flipping) {
      setShowFace(false);
      // reveal face after the flip animation midpoint
      timerRef.current = setTimeout(() => setShowFace(true), 380);
    } else {
      setShowFace(true);
    }
    return () => clearTimeout(timerRef.current);
  }, [card, flipping]);

  const remaining = card?.remaining ?? 350;

  return (
    <div className="card-broadcast p-4" data-testid="flippy-deck">
      <div className="flex items-center justify-between mb-3">
        <div className="flex items-center gap-2">
          <Layers size={14} className="text-fuchsia-300" />
          <span className="text-[10px] font-mono uppercase tracking-widest text-slate-300">
            Flippy Deck
          </span>
        </div>
        <span
          data-testid="flippy-remaining"
          className="text-[10px] font-mono tabular-nums px-2 py-0.5 rounded bg-white/5 border border-white/10 text-slate-300"
        >
          {remaining}/350
        </span>
      </div>

      <div className="flippy-stage" data-testid="flippy-stage">
        <div className={`flippy-card ${showFace ? "is-face" : "is-back"}`}>
          {/* Back (pre-snap / flipping) */}
          <div className="flippy-face flippy-back">
            <div className="flippy-back-inner">
              <div className="flippy-diamond" />
              <div className="text-[9px] font-mono uppercase tracking-[0.3em] text-fuchsia-200/80">
                Flippy Deck
              </div>
            </div>
          </div>

          {/* Front (revealed) */}
          <div className="flippy-face flippy-front">
            {card && <CardFace card={card} />}
          </div>
        </div>
      </div>
    </div>
  );
}

function CardFace({ card }) {
  if (card.type === "DICE") {
    return (
      <div className="flippy-front-inner text-center">
        <Zap size={22} className="text-sky-300 mb-1" />
        <div className="text-[9px] font-mono uppercase tracking-[0.28em] text-slate-400">
          Card says
        </div>
        <div className="text-2xl font-display font-black tracking-tight text-sky-300 mt-1 leading-none">
          ROLL DICE!
        </div>
        <div className="text-[10px] font-mono uppercase tracking-widest text-slate-500 mt-2">
          Standard play
        </div>
      </div>
    );
  }
  if (card.type === "INJURY") {
    const inj = card.injury || {};
    return (
      <div
        className="flippy-front-inner text-center"
        data-testid="flippy-injury-card"
      >
        <AlertTriangle size={22} className="text-rose-300 mb-1" />
        <div className="text-[9px] font-mono uppercase tracking-[0.28em] text-rose-200">
          Concussion
        </div>
        <div className="text-lg font-display font-black tracking-tight text-rose-300 mt-1 leading-tight">
          {inj.player || "Player"}
        </div>
        <div className="text-[10px] font-mono uppercase tracking-widest text-rose-300/80 mt-1">
          {inj.pos || "?"} • OUT FOR GAME
        </div>
        {inj.replacement && (
          <div className="text-[9px] font-mono text-slate-400 mt-1">
            In → {inj.replacement}
          </div>
        )}
      </div>
    );
  }
  // YARDS
  const y = card.yards ?? 0;
  const isSignature = !!card.signature;
  const isBreakaway = y >= 30;
  const isChunk = y >= 15 && y < 30;
  const isBig = y >= 8 && y < 15;
  const isLoss = y < 0;
  const color = isSignature
    ? "text-fuchsia-300"
    : isBreakaway
    ? "text-emerald-300"
    : isChunk
    ? "text-emerald-300"
    : isBig
    ? "text-lime-300"
    : isLoss
    ? "text-rose-300"
    : "text-amber-300";
  const label = isSignature
    ? card.label || "Signature"
    : isBreakaway
    ? "BREAKAWAY"
    : isChunk
    ? "Chunk Play"
    : isLoss
    ? "Behind the Line"
    : "Yards";
  return (
    <div
      className="flippy-front-inner text-center"
      data-testid={isSignature ? "flippy-signature-card" : "flippy-yards-card"}
    >
      <div className="text-[9px] font-mono uppercase tracking-[0.28em] text-slate-400">
        {isSignature ? "Signature Card" : "Card says"}
      </div>
      <div
        className={`text-5xl font-display font-black tracking-tighter tabular-nums mt-1 leading-none ${color}`}
      >
        {y > 0 ? `+${y}` : y}
      </div>
      <div className={`text-[10px] font-mono uppercase tracking-widest mt-2 ${color}`}>
        {label}
      </div>
      <div className="text-[9px] font-mono text-slate-500 mt-1">
        {isSignature ? "Franchise pick" : "Skip the dice chart"}
      </div>
    </div>
  );
}
