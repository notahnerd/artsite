-- Gridiron Roller — MySQL schema (shared-hosting friendly, no fancy features)
-- Usage: mysql -u user -p flippy_football < schema.sql

SET NAMES utf8mb4;

DROP TABLE IF EXISTS seasons;
CREATE TABLE seasons (
    id                CHAR(36)     NOT NULL PRIMARY KEY,
    owner_token       CHAR(64)     NOT NULL,
    user_team         VARCHAR(4)   NOT NULL,
    year              SMALLINT     NOT NULL DEFAULT 2025,
    difficulty        VARCHAR(16)  NOT NULL DEFAULT 'balanced',
    current_week      SMALLINT     NOT NULL DEFAULT 1,
    schedule_json     LONGTEXT     NOT NULL,     -- JSON blob for schedule + game state
    depth_charts_json TEXT         NULL,
    injuries_json     TEXT         NULL,
    trades_json       TEXT         NULL,
    coaching_json     TEXT         NULL,
    playbook_json     TEXT         NULL,
    extra_rivals_json TEXT         NULL,
    deck_config_json  TEXT         NULL,
    playoffs_json     LONGTEXT     NULL,
    champions_history_json TEXT    NULL,
    franchise_rosters_json LONGTEXT NULL,
    franchise_id      CHAR(36)     NULL,
    created_at        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_franchise (franchise_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS game_logs;
CREATE TABLE game_logs (
    id           CHAR(36)  NOT NULL PRIMARY KEY,
    season_id    CHAR(36)  NOT NULL,
    game_id      VARCHAR(64) NOT NULL,
    home_team    VARCHAR(4)  NOT NULL,
    away_team    VARCHAR(4)  NOT NULL,
    home_score   SMALLINT    NOT NULL,
    away_score   SMALLINT    NOT NULL,
    plays_json   LONGTEXT    NOT NULL,
    box_json     LONGTEXT    NULL,
    created_at   TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_season (season_id),
    KEY idx_game (game_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS rate_limit_hits;
CREATE TABLE rate_limit_hits (
    ip_bucket   VARCHAR(64) NOT NULL,
    hit_at      TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_bucket_time (ip_bucket, hit_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
