-- Fenor — platform settings table
-- Compatible with PostgreSQL and MySQL

CREATE TABLE IF NOT EXISTS fenor_settings (
    key        VARCHAR(100) NOT NULL,
    value      TEXT         NOT NULL DEFAULT '',
    updated_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (key)
);

-- Default values (replaced by install.sh with real values)
INSERT INTO fenor_settings (key, value) VALUES
    ('BASE_DOMAIN',          '')
  , ('ADMIN_EMAIL',          '')
  , ('ADMIN_PASSWORD_HASH',  '')
  , ('APPS_PATH',            '/var/www')
  , ('TERMINAL_URL',         '')
  , ('CF_TOKEN',             '')
  , ('CF_ZONE_ID',           '')
  , ('CF_TUNNEL_ID',         '')
  , ('GITHUB_TOKEN',         '')
  , ('GITHUB_ORG',           '')
  , ('ANTHROPIC_API_KEY',    '')
ON CONFLICT (key) DO NOTHING;

-- Metadata for apps created by the platform
CREATE TABLE IF NOT EXISTS fenor_apps (
    id           SERIAL PRIMARY KEY,
    name         VARCHAR(100) NOT NULL UNIQUE,
    description  TEXT         NOT NULL DEFAULT '',
    github_repo  VARCHAR(255) NOT NULL DEFAULT '',
    memory_notes TEXT         NOT NULL DEFAULT '',
    language     VARCHAR(5)   NOT NULL DEFAULT 'pt',   -- pt | en
    status       VARCHAR(20)  NOT NULL DEFAULT 'registered',
    config       JSONB,
    created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);
