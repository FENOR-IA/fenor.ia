-- Fenor — tabela de configurações da plataforma
-- Compatível com PostgreSQL e MySQL

CREATE TABLE IF NOT EXISTS fenor_settings (
    key        VARCHAR(100) NOT NULL,
    value      TEXT         NOT NULL DEFAULT '',
    updated_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (key)
);

-- Valores padrão (substituídos pelo install.sh com os valores reais)
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

-- Metadados dos apps criados pela plataforma
CREATE TABLE IF NOT EXISTS fenor_apps (
    id           SERIAL PRIMARY KEY,
    name         VARCHAR(100) NOT NULL UNIQUE,
    description  TEXT         NOT NULL DEFAULT '',
    github_repo  VARCHAR(255) NOT NULL DEFAULT '',
    memory_notes TEXT         NOT NULL DEFAULT '',
    status       VARCHAR(20)  NOT NULL DEFAULT 'registered',
    created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);
