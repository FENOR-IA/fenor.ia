-- ═══════════════════════════════════════════════════
-- FENOR APP — Initial database schema
-- Executed automatically by newapp
-- ═══════════════════════════════════════════════════

-- System users
CREATE TABLE IF NOT EXISTS users (
    id            SERIAL PRIMARY KEY,
    name          VARCHAR(200) NOT NULL,
    email         VARCHAR(200) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role          VARCHAR(20)  NOT NULL DEFAULT 'user',
    active        BOOLEAN      NOT NULL DEFAULT true,
    avatar        TEXT,
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Customers
CREATE TABLE IF NOT EXISTS customers (
    id         SERIAL PRIMARY KEY,
    name       VARCHAR(200) NOT NULL,
    email      VARCHAR(200),
    phone      VARCHAR(30),
    document   VARCHAR(30),
    address    TEXT,
    notes      TEXT,
    active     BOOLEAN   NOT NULL DEFAULT true,
    user_id    INTEGER   REFERENCES users(id),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Financial transactions (accounts payable / receivable)
CREATE TABLE IF NOT EXISTS transactions (
    id          SERIAL PRIMARY KEY,
    type        VARCHAR(20)   NOT NULL CHECK (type IN ('income', 'expense')),
    description TEXT          NOT NULL,
    amount      NUMERIC(12,2) NOT NULL,
    entry_date  DATE          NOT NULL,
    due_date    DATE,
    status      VARCHAR(20)   NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'paid', 'cancelled')),
    customer_id INTEGER       REFERENCES customers(id),
    user_id     INTEGER       REFERENCES users(id),
    notes       TEXT,
    created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Indexes
CREATE INDEX IF NOT EXISTS idx_customers_name     ON customers(name);
CREATE INDEX IF NOT EXISTS idx_customers_active   ON customers(active);
CREATE INDEX IF NOT EXISTS idx_transactions_type   ON transactions(type);
CREATE INDEX IF NOT EXISTS idx_transactions_status ON transactions(status);
CREATE INDEX IF NOT EXISTS idx_transactions_date   ON transactions(entry_date);

-- Initial admin user (password inserted by newapp via psql)
-- INSERT INTO users (name, email, password_hash, role) VALUES (...)
