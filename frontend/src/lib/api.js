import axios from "axios";

const API = `${process.env.REACT_APP_BACKEND_URL}/api`;
export const api = axios.create({ baseURL: API });

// ---------- Owner token storage (per-season capability) ----------
const TOKEN_KEY = "nfl_owner_tokens";

function _readTokens() {
  try {
    return JSON.parse(localStorage.getItem(TOKEN_KEY) || "{}") || {};
  } catch {
    return {};
  }
}

export function saveOwnerToken(seasonId, token) {
  if (!seasonId || !token) return;
  const map = _readTokens();
  map[seasonId] = token;
  try {
    localStorage.setItem(TOKEN_KEY, JSON.stringify(map));
  } catch {}
}

export function getOwnerToken(seasonId) {
  if (!seasonId) return null;
  return _readTokens()[seasonId] || null;
}

// Attach X-Owner-Token header for any request that references a season id
api.interceptors.request.use((config) => {
  try {
    const url = (config.url || "").toString();
    let sid = null;
    // Body-based season_id (POST payloads)
    if (config.data && typeof config.data === "object" && config.data.season_id) {
      sid = config.data.season_id;
    }
    // URL-based /season/{id}/...
    if (!sid) {
      const m = url.match(/\/season\/([0-9a-f-]{36})/i);
      if (m) sid = m[1];
    }
    if (sid) {
      const tok = getOwnerToken(sid);
      if (tok) {
        config.headers = config.headers || {};
        config.headers["X-Owner-Token"] = tok;
      }
    }
  } catch {}
  return config;
});

// ---------- API wrappers ----------
export const listTeams = () => api.get("/teams").then((r) => r.data.teams);
export const getTeam = (id) => api.get(`/teams/${id}`).then((r) => r.data);
export const getChart = () => api.get("/chart").then((r) => r.data);
export const createSeason = (user_team) =>
  api.post("/season/create", { user_team, year: 2025 }).then((r) => {
    const data = r.data;
    if (data && data.id && data.owner_token) {
      saveOwnerToken(data.id, data.owner_token);
    }
    return data;
  });
export const getSeason = (id) => api.get(`/season/${id}`).then((r) => r.data);
export const simGame = (season_id, game_id) =>
  api.post("/season/sim-game", { season_id, game_id }).then((r) => r.data);
export const simWeek = (season_id, week) =>
  api.post("/season/sim-week", { season_id, week }).then((r) => r.data);
export const getGameLog = (log_id) => api.get(`/game-log/${log_id}`).then((r) => r.data);
