-- ============================================================
-- Fenor — Template PT — Banco de dados inicial
-- ============================================================

-- Usuários do sistema
CREATE TABLE IF NOT EXISTS users (
    id           SERIAL PRIMARY KEY,
    name         VARCHAR(150) NOT NULL,
    email        VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role         VARCHAR(20)  NOT NULL DEFAULT 'user',  -- admin | user
    active       BOOLEAN      NOT NULL DEFAULT true,
    created_at   TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_at   TIMESTAMP    NOT NULL DEFAULT NOW()
);

-- Clientes
CREATE TABLE IF NOT EXISTS customers (
    id         SERIAL PRIMARY KEY,
    name       VARCHAR(150) NOT NULL,
    email      VARCHAR(150),
    phone      VARCHAR(30),
    document   VARCHAR(30),              -- CPF / CNPJ
    address    TEXT,
    notes      TEXT,
    active     BOOLEAN   NOT NULL DEFAULT true,
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP NOT NULL DEFAULT NOW()
);

-- Lançamentos financeiros
CREATE TABLE IF NOT EXISTS transactions (
    id          SERIAL PRIMARY KEY,
    customer_id INT REFERENCES customers(id) ON DELETE SET NULL,
    description VARCHAR(255) NOT NULL,
    type        VARCHAR(10)  NOT NULL DEFAULT 'income',   -- income | expense
    amount      NUMERIC(12,2) NOT NULL DEFAULT 0,
    status      VARCHAR(15)  NOT NULL DEFAULT 'pending', -- pending | paid | cancelled
    entry_date  DATE         NOT NULL DEFAULT CURRENT_DATE,
    due_date    DATE,
    notes       TEXT,
    created_at  TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMP    NOT NULL DEFAULT NOW()
);
