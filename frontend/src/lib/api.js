import axios from "axios";

const API = `${process.env.REACT_APP_BACKEND_URL}/api`;
export const api = axios.create({ baseURL: API });

export const listTeams = () => api.get("/teams").then((r) => r.data.teams);
export const getTeam = (id) => api.get(`/teams/${id}`).then((r) => r.data);
export const getChart = () => api.get("/chart").then((r) => r.data);
export const createSeason = (user_team) =>
  api.post("/season/create", { user_team, year: 2025 }).then((r) => r.data);
export const getSeason = (id) => api.get(`/season/${id}`).then((r) => r.data);
export const simGame = (season_id, game_id) =>
  api.post("/season/sim-game", { season_id, game_id }).then((r) => r.data);
export const simWeek = (season_id, week) =>
  api.post("/season/sim-week", { season_id, week }).then((r) => r.data);
export const getGameLog = (log_id) => api.get(`/game-log/${log_id}`).then((r) => r.data);
