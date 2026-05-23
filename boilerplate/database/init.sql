-- ═══════════════════════════════════════════════════
-- FENOR APP — Banco de dados inicial
-- Executado automaticamente pelo newapp
-- ═══════════════════════════════════════════════════

-- Usuários do sistema
CREATE TABLE IF NOT EXISTS users (
    id         SERIAL PRIMARY KEY,
    nome       VARCHAR(200) NOT NULL,
    email      VARCHAR(200) NOT NULL UNIQUE,
    senha_hash VARCHAR(255) NOT NULL,
    role       VARCHAR(20)  NOT NULL DEFAULT 'user',
    ativo      BOOLEAN      NOT NULL DEFAULT true,
    avatar     TEXT,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Clientes
CREATE TABLE IF NOT EXISTS clientes (
    id          SERIAL PRIMARY KEY,
    nome        VARCHAR(200) NOT NULL,
    email       VARCHAR(200),
    telefone    VARCHAR(30),
    documento   VARCHAR(30),
    endereco    TEXT,
    observacoes TEXT,
    ativo       BOOLEAN   NOT NULL DEFAULT true,
    user_id     INTEGER   REFERENCES users(id),
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Lançamentos financeiros (contas a pagar e receber)
CREATE TABLE IF NOT EXISTS lancamentos (
    id               SERIAL PRIMARY KEY,
    tipo             VARCHAR(20)    NOT NULL CHECK (tipo IN ('receita','despesa')),
    descricao        TEXT           NOT NULL,
    valor            NUMERIC(12,2)  NOT NULL,
    data_lancamento  DATE           NOT NULL,
    data_vencimento  DATE,
    status           VARCHAR(20)    NOT NULL DEFAULT 'pendente' CHECK (status IN ('pendente','pago','cancelado')),
    cliente_id       INTEGER        REFERENCES clientes(id),
    user_id          INTEGER        REFERENCES users(id),
    observacoes      TEXT,
    created_at       TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Índices
CREATE INDEX IF NOT EXISTS idx_clientes_nome     ON clientes(nome);
CREATE INDEX IF NOT EXISTS idx_clientes_ativo    ON clientes(ativo);
CREATE INDEX IF NOT EXISTS idx_lancamentos_tipo   ON lancamentos(tipo);
CREATE INDEX IF NOT EXISTS idx_lancamentos_status ON lancamentos(status);
CREATE INDEX IF NOT EXISTS idx_lancamentos_data   ON lancamentos(data_lancamento);

-- Admin inicial (senha inserida pelo newapp via psql)
-- INSERT INTO users (nome, email, senha_hash, role) VALUES (...)
