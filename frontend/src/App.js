import React from "react";
import { BrowserRouter, Routes, Route } from "react-router-dom";
import { Toaster } from "sonner";
import Home from "@/pages/Home";
import Season from "@/pages/Season";
import Game from "@/pages/Game";
import Chart from "@/pages/Chart";

function App() {
  return (
    <div className="min-h-screen bg-[#070A10] text-slate-100">
      <BrowserRouter>
        <Routes>
          <Route path="/" element={<Home />} />
          <Route path="/season/:id" element={<Season />} />
          <Route path="/season/:id/game/:gameId" element={<Game />} />
          <Route path="/season/:id/chart" element={<Chart />} />
        </Routes>
      </BrowserRouter>
      <Toaster
        theme="dark"
        position="top-right"
        toastOptions={{
          style: {
            background: "rgba(13, 19, 34, 0.95)",
            color: "#F8FAFC",
            border: "1px solid rgba(245, 158, 11, 0.3)",
          },
        }}
      />
    </div>
  );
}

export default App;
