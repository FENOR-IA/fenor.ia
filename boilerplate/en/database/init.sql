-- ============================================================
-- Fenor — EN Template — Initial database schema
-- ============================================================

-- Users table (required for authentication)
CREATE TABLE IF NOT EXISTS users (
    id            SERIAL PRIMARY KEY,
    name          VARCHAR(150) NOT NULL,
    email         VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role          VARCHAR(20)  NOT NULL DEFAULT 'user',  -- admin | user
    active        BOOLEAN      NOT NULL DEFAULT true,
    created_at    TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_at    TIMESTAMP    NOT NULL DEFAULT NOW()
);

-- ─── TODO: add your tables here ──────────────────────────────────────────────
-- Example:
-- CREATE TABLE IF NOT EXISTS contacts (
--     id         SERIAL PRIMARY KEY,
--     name       VARCHAR(150) NOT NULL,
--     email      VARCHAR(150),
--     created_at TIMESTAMP NOT NULL DEFAULT NOW()
-- );
