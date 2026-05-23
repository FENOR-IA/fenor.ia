#!/bin/bash
# ═══════════════════════════════════════════════════════
# FENOR — Instalação da Infraestrutura
# Ubuntu 24.04 LTS
# Uso: bash <(curl -fsSL https://fenor.ia.br/install.sh)
# ═══════════════════════════════════════════════════════

set -e

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; NC='\033[0m'
ok()   { echo -e "  ${GREEN}✓${NC} $1"; }
warn() { echo -e "  ${YELLOW}!${NC} $1"; }
fail() { echo -e "  ${RED}✗${NC} $1"; exit 1; }

echo ""
echo "  ███████╗███████╗███╗   ██╗ ██████╗ ██████╗ "
echo "  ██╔════╝██╔════╝████╗  ██║██╔═══██╗██╔══██╗"
echo "  █████╗  █████╗  ██╔██╗ ██║██║   ██║██████╔╝"
echo "  ██╔══╝  ██╔══╝  ██║╚██╗██║██║   ██║██╔══██╗"
echo "  ██║     ███████╗██║ ╚████║╚██████╔╝██║  ██║"
echo "  ╚═╝     ╚══════╝╚═╝  ╚═══╝ ╚═════╝ ╚═╝  ╚═╝"
echo ""
echo "  Instalando infraestrutura Fenor..."
echo ""

# ── PRÉ-REQUISITOS ────────────────────────────────────
[ "$(id -u)" -eq 0 ] || fail "Execute como root: sudo bash install.sh"
. /etc/os-release 2>/dev/null || true
[[ "$ID" == "ubuntu" ]] || warn "Testado no Ubuntu. Outros sistemas podem funcionar."

# ── DETECÇÃO DO AMBIENTE ──────────────────────────────
echo "  Detectando ambiente..."

# Web server
WEB_SERVER=""
command -v nginx   &>/dev/null && WEB_SERVER="nginx"
command -v apache2 &>/dev/null && [ -z "$WEB_SERVER" ] && WEB_SERVER="apache2"

# PHP e socket FPM
PHP_VERSION=""
PHP_FPM_SOCK=""
for v in 8.3 8.2 8.1 8.0 7.4; do
  if command -v "php$v" &>/dev/null; then PHP_VERSION="$v"; break; fi
done
if [ -z "$PHP_VERSION" ] && command -v php &>/dev/null; then
  PHP_VERSION=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;" 2>/dev/null)
fi
if [ -n "$PHP_VERSION" ]; then
  for sock in "/run/php/php${PHP_VERSION}-fpm.sock" "/var/run/php/php${PHP_VERSION}-fpm.sock"; do
    [ -S "$sock" ] && PHP_FPM_SOCK="$sock" && break
  done
fi

# Banco de dados
DB_DETECTED=""
command -v psql  &>/dev/null && DB_DETECTED="pgsql"
command -v mysql &>/dev/null && [ -z "$DB_DETECTED" ] && DB_DETECTED="mysql"

# Node.js + Claude
HAS_NODE=false;   command -v node   &>/dev/null && HAS_NODE=true
HAS_CLAUDE=false; command -v claude &>/dev/null && HAS_CLAUDE=true

echo ""
echo "  ┌─ Encontrado ─────────────────────────────┐"
[ -n "$WEB_SERVER"  ] && echo "  │  Web server : $WEB_SERVER"     || echo "  │  Web server : não instalado"
[ -n "$PHP_VERSION" ] && echo "  │  PHP        : $PHP_VERSION"    || echo "  │  PHP        : não instalado"
[ -n "$PHP_FPM_SOCK" ] && echo "  │  PHP-FPM    : $PHP_FPM_SOCK"  || echo "  │  PHP-FPM    : socket não encontrado"
[ -n "$DB_DETECTED" ] && echo "  │  Banco      : $DB_DETECTED"    || echo "  │  Banco      : não instalado"
$HAS_NODE           && echo "  │  Node.js    : $(node -v)"        || echo "  │  Node.js    : não instalado"
$HAS_CLAUDE         && echo "  │  Claude     : $(claude --version 2>/dev/null | head -1)" || echo "  │  Claude     : não instalado"
echo "  └──────────────────────────────────────────┘"
echo ""

# ── VARIÁVEIS ─────────────────────────────────────────
# Garante que os reads capturam do terminal mesmo quando executado via pipe
if ! [ -t 0 ]; then
  exec < /dev/tty 2>/dev/null || fail "Sem terminal interativo. Use: bash <(curl -fsSL https://fenor.ia.br/install.sh)"
fi

echo "  Configure sua instalação:"
echo ""
read -p "  Domínio base (ex: meusite.com ou fenor.local): " BASE_DOMAIN
read -p "  Email do admin: "                                  ADMIN_EMAIL
echo ""
echo "  Cloudflare (opcional — deixe em branco para ambiente local):"
read -p "  CF API Token: "  CF_TOKEN
read -p "  CF Zone ID: "    CF_ZONE_ID
read -p "  CF Tunnel ID: "  CF_TUNNEL_ID
echo ""

# Banco de dados
if [ -n "$DB_DETECTED" ]; then
  echo "  Banco detectado: $DB_DETECTED"
  read -p "  Usar banco existente? [S/n]: " USE_EXISTING_DB
  if [[ "$USE_EXISTING_DB" =~ ^[Nn]$ ]]; then DB_DETECTED=""; fi
fi
if [ -z "$DB_DETECTED" ]; then
  read -p "  Driver [1] PostgreSQL  [2] MySQL (padrão: 1): " DB_CHOICE
  [ "$DB_CHOICE" = "2" ] && DB_DETECTED="mysql" || DB_DETECTED="pgsql"
  [ "$DB_DETECTED" = "mysql" ] && read -s -p "  Senha root MySQL: " DB_ROOT_PASS && echo ""
fi
DB_DRIVER="$DB_DETECTED"
DB_STUDIO_PASS=$(openssl rand -base64 16 | tr -dc 'a-zA-Z0-9' | head -c 20)
DB_APPS_VIEWER_PASS=$(openssl rand -base64 16 | tr -dc 'a-zA-Z0-9' | head -c 20)

read -s -p "  Senha do painel Studio: " ADMIN_PASS
echo ""

ADMIN_HASH=$(php -r "echo password_hash('$ADMIN_PASS', PASSWORD_BCRYPT);" 2>/dev/null \
  || python3 -c "import bcrypt; print(bcrypt.hashpw(b'$ADMIN_PASS', bcrypt.gensalt()).decode())" 2>/dev/null \
  || echo "")
[ -z "$ADMIN_HASH" ] && fail "Não foi possível gerar hash da senha. PHP ou Python3+bcrypt necessário."

echo ""
echo "  ────────────────────────────────────────"

# ── 1. SISTEMA ────────────────────────────────────────
echo ""
echo "  [1/8] Sistema..."
DEBIAN_FRONTEND=noninteractive apt update -qq
DEBIAN_FRONTEND=noninteractive apt install -y -qq git curl wget unzip software-properties-common
ok "Dependências básicas verificadas"

# ── 2. WEB SERVER + PHP ───────────────────────────────
echo "  [2/8] Web server + PHP..."

if [ -z "$WEB_SERVER" ]; then
  # Instala Nginx
  DEBIAN_FRONTEND=noninteractive apt install -y -qq nginx
  WEB_SERVER="nginx"
  systemctl enable nginx &>/dev/null
  systemctl start nginx
  ok "Nginx instalado"
else
  ok "Web server existente: $WEB_SERVER (mantido)"
fi

if [ -z "$PHP_VERSION" ]; then
  # Instala PHP 8.2 via PPA
  add-apt-repository -y ppa:ondrej/php &>/dev/null
  apt update -qq
  if [ "$DB_DRIVER" = "mysql" ]; then
    DEBIAN_FRONTEND=noninteractive apt install -y -qq php8.2-fpm php8.2-mysql php8.2-curl php8.2-mbstring php8.2-xml
  else
    DEBIAN_FRONTEND=noninteractive apt install -y -qq php8.2-fpm php8.2-pgsql php8.2-curl php8.2-mbstring php8.2-xml
  fi
  PHP_VERSION="8.2"
  PHP_FPM_SOCK="/run/php/php8.2-fpm.sock"
  systemctl enable php8.2-fpm &>/dev/null
  systemctl start php8.2-fpm
  ok "PHP 8.2 instalado"
else
  # Garante extensões necessárias
  if [ "$DB_DRIVER" = "mysql" ]; then
    DEBIAN_FRONTEND=noninteractive apt install -y -qq php${PHP_VERSION}-mysql php${PHP_VERSION}-curl php${PHP_VERSION}-mbstring php${PHP_VERSION}-xml &>/dev/null || true
  else
    DEBIAN_FRONTEND=noninteractive apt install -y -qq php${PHP_VERSION}-pgsql php${PHP_VERSION}-curl php${PHP_VERSION}-mbstring php${PHP_VERSION}-xml &>/dev/null || true
  fi
  # Garante PHP-FPM rodando
  systemctl enable php${PHP_VERSION}-fpm &>/dev/null || true
  systemctl start  php${PHP_VERSION}-fpm 2>/dev/null || true
  # Encontra socket
  for sock in "/run/php/php${PHP_VERSION}-fpm.sock" "/var/run/php/php${PHP_VERSION}-fpm.sock"; do
    [ -S "$sock" ] && PHP_FPM_SOCK="$sock" && break
  done
  ok "PHP $PHP_VERSION existente (mantido) — socket: $PHP_FPM_SOCK"
fi

if [ "$DB_DRIVER" = "pgsql" ]; then
  # Checa servidor (usuário postgres), não apenas o cliente psql
  if ! id postgres &>/dev/null; then
    DEBIAN_FRONTEND=noninteractive apt install -y -qq postgresql postgresql-contrib
    systemctl enable postgresql &>/dev/null
    systemctl start postgresql
    ok "PostgreSQL instalado"
  else
    systemctl enable postgresql &>/dev/null
    systemctl start postgresql 2>/dev/null || true
    ok "PostgreSQL existente (mantido)"
  fi
fi

# ── 3. NODE.JS + CLAUDE CODE ──────────────────────────
echo "  [3/8] Node.js + Claude Code..."
if ! $HAS_NODE; then
  curl -fsSL https://deb.nodesource.com/setup_20.x | bash - &>/dev/null
  DEBIAN_FRONTEND=noninteractive apt install -y -qq nodejs
  ok "Node.js $(node -v) instalado"
else
  ok "Node.js existente: $(node -v) (mantido)"
fi
if ! $HAS_CLAUDE; then
  npm install -g @anthropic-ai/claude-code &>/dev/null
  ok "Claude Code instalado"
else
  ok "Claude Code existente (mantido)"
fi
# Garante que claude está acessível a todos os usuários do sistema
CLAUDE_BIN=$(which claude 2>/dev/null)
if [ -n "$CLAUDE_BIN" ]; then
  CLAUDE_REAL=$(readlink -f "$CLAUDE_BIN" 2>/dev/null || echo "$CLAUDE_BIN")
  if [ "$CLAUDE_REAL" != "/usr/local/bin/claude" ] && [ ! -f "/opt/claude" ]; then
    cp "$CLAUDE_REAL" /opt/claude 2>/dev/null || true
    chmod 755 /opt/claude 2>/dev/null || true
    ln -sf /opt/claude /usr/local/bin/claude 2>/dev/null || true
  fi
  chmod 755 "$CLAUDE_BIN" 2>/dev/null || true
  ok "Claude acessível globalmente em /usr/local/bin/claude"
fi

# ── 4. TTYD ───────────────────────────────────────────
echo "  [4/8] Terminal web (ttyd)..."
if ! command -v ttyd &>/dev/null; then
  ARCH=$(uname -m)
  [ "$ARCH" = "x86_64" ] && TTYD_BIN="ttyd.x86_64" || TTYD_BIN="ttyd.aarch64"
  wget -q "https://github.com/tsl0922/ttyd/releases/download/1.7.7/$TTYD_BIN" \
    -O /usr/local/bin/ttyd
  chmod +x /usr/local/bin/ttyd
  ok "ttyd instalado"
else
  ok "ttyd existente (mantido)"
fi

id fenor &>/dev/null || useradd -m -s /bin/bash fenor

if ! systemctl is-active ttyd &>/dev/null; then
  cat > /etc/systemd/system/ttyd.service << EOF
[Unit]
Description=ttyd terminal web - Fenor
After=network.target

[Service]
User=fenor
ExecStart=/usr/local/bin/ttyd --port 7681 --interface 127.0.0.1 --writable bash
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
EOF
  systemctl daemon-reload
  systemctl enable ttyd &>/dev/null
  systemctl start ttyd
fi
ok "ttyd ativo (porta 127.0.0.1:7681)"

# ── 5. BANCO DE DADOS ────────────────────────────────
echo "  [5/8] Banco de dados..."

if [ "$DB_DRIVER" = "mysql" ]; then
    mysql -u root -p"$DB_ROOT_PASS" <<SQL 2>/dev/null
CREATE DATABASE IF NOT EXISTS fenor CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'fenor_studio'@'localhost' IDENTIFIED BY '$DB_STUDIO_PASS';
GRANT ALL PRIVILEGES ON fenor.* TO 'fenor_studio'@'localhost';
FLUSH PRIVILEGES;
USE fenor;
CREATE TABLE IF NOT EXISTS fenor_settings (
    \`key\`      VARCHAR(100) NOT NULL,
    value       TEXT         NOT NULL DEFAULT '',
    updated_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (\`key\`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO fenor_settings (\`key\`, value) VALUES
    ('BASE_DOMAIN','$BASE_DOMAIN'),('ADMIN_EMAIL','$ADMIN_EMAIL'),
    ('ADMIN_PASSWORD_HASH','$ADMIN_HASH'),('APPS_PATH','/var/www'),
    ('TERMINAL_URL','https://terminal.$BASE_DOMAIN'),
    ('CF_TOKEN','$CF_TOKEN'),('CF_ZONE_ID','$CF_ZONE_ID'),('CF_TUNNEL_ID','$CF_TUNNEL_ID'),
    ('GITHUB_TOKEN',''),('GITHUB_ORG',''),
    ('ANTHROPIC_API_KEY','');
CREATE TABLE IF NOT EXISTS fenor_apps (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(100) NOT NULL UNIQUE,
    description  TEXT         NOT NULL DEFAULT '',
    github_repo  VARCHAR(255) NOT NULL DEFAULT '',
    memory_notes TEXT         NOT NULL DEFAULT '',
    status       VARCHAR(20)  NOT NULL DEFAULT 'registered',
    created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
GRANT ALL PRIVILEGES ON fenor.* TO 'fenor_studio'@'localhost';
SQL
    ok "MySQL: database fenor + tabelas criadas"
else
    su - postgres -c "psql -c \"CREATE DATABASE fenor;\"" &>/dev/null || true
    su - postgres -c "psql -c \"CREATE USER fenor_studio WITH PASSWORD '$DB_STUDIO_PASS';\"" &>/dev/null || true
    su - postgres -c "psql -c \"GRANT ALL PRIVILEGES ON DATABASE fenor TO fenor_studio;\"" &>/dev/null || true
    su - postgres -c "psql -d fenor" <<SQL
CREATE TABLE IF NOT EXISTS fenor_settings (
    key        VARCHAR(100) NOT NULL,
    value      TEXT         NOT NULL DEFAULT '',
    updated_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (key)
);
INSERT INTO fenor_settings (key, value) VALUES
    ('BASE_DOMAIN','$BASE_DOMAIN'),('ADMIN_EMAIL','$ADMIN_EMAIL'),
    ('ADMIN_PASSWORD_HASH','$ADMIN_HASH'),('APPS_PATH','/var/www'),
    ('TERMINAL_URL','https://terminal.$BASE_DOMAIN'),
    ('CF_TOKEN','$CF_TOKEN'),('CF_ZONE_ID','$CF_ZONE_ID'),('CF_TUNNEL_ID','$CF_TUNNEL_ID'),
    ('GITHUB_TOKEN',''),('GITHUB_ORG',''),
    ('ANTHROPIC_API_KEY','')
ON CONFLICT (key) DO NOTHING;
CREATE TABLE IF NOT EXISTS fenor_apps (
    id           SERIAL PRIMARY KEY,
    name         VARCHAR(100) NOT NULL UNIQUE,
    description  TEXT         NOT NULL DEFAULT '',
    github_repo  VARCHAR(255) NOT NULL DEFAULT '',
    memory_notes TEXT         NOT NULL DEFAULT '',
    status       VARCHAR(20)  NOT NULL DEFAULT 'registered',
    created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);
GRANT ALL ON TABLE fenor_settings TO fenor_studio;
GRANT ALL ON TABLE fenor_apps TO fenor_studio;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO fenor_studio;

-- fenor_apps_viewer: acesso somente aos schemas de apps (não ao schema public)
DO \$\$ BEGIN
  IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'fenor_apps_viewer') THEN
    CREATE USER fenor_apps_viewer WITH PASSWORD '$DB_APPS_VIEWER_PASS';
  ELSE
    ALTER USER fenor_apps_viewer WITH PASSWORD '$DB_APPS_VIEWER_PASS';
  END IF;
END \$\$;
REVOKE ALL ON SCHEMA public FROM fenor_apps_viewer;
REVOKE ALL ON ALL TABLES IN SCHEMA public FROM fenor_apps_viewer;
SQL
    ok "PostgreSQL: database fenor + tabelas criadas"
fi

# ── 6. DIRETÓRIOS ─────────────────────────────────────
echo "  [6/8] Diretórios..."
mkdir -p /var/www/{dev,hml,prd,studio}
chown -R fenor:www-data /var/www/
chmod -R 775 /var/www/
mkdir -p /etc/fenor/keys
chown root:www-data /etc/fenor/keys
chmod 750 /etc/fenor/keys
ok "Diretórios /var/www/{dev,hml,prd,studio} e /etc/fenor/keys criados"

# ── 7. NGINX ──────────────────────────────────────────
echo "  [7/8] Nginx..."
rm -f /etc/nginx/sites-enabled/default

cat > /etc/nginx/sites-available/fenor.conf << NGINX
server {
    listen 80;
    server_name $BASE_DOMAIN studio.$BASE_DOMAIN;
    root /var/www/studio;
    index login.php dashboard.php index.html;
    location / { try_files \$uri \$uri/ /login.php?\$query_string; }
    location ~ \.php$ { include snippets/fastcgi-php.conf; fastcgi_pass unix:$PHP_FPM_SOCK; }
    location ~ ^/(config|storage|\.env) { deny all; return 404; }
}
server {
    listen 80;
    server_name ~^(?<app>[^.]+)\.dev\.$BASE_DOMAIN\$;
    index index.php index.html;
    location / {
        root /var/www/dev/\$app/front;
        try_files \$uri \$uri/ /index.php?\$query_string;
        location ~ \.php$ { root /var/www/dev/\$app/front; include snippets/fastcgi-php.conf; fastcgi_pass unix:$PHP_FPM_SOCK; }
    }
    location /back/ {
        root /var/www/dev/\$app;
        try_files \$uri \$uri/ /back/index.php?\$query_string;
        location ~ \.php$ { root /var/www/dev/\$app; include snippets/fastcgi-php.conf; fastcgi_pass unix:$PHP_FPM_SOCK; }
    }
    location /api/ {
        root /var/www/dev/\$app;
        try_files \$uri \$uri/ /back/index.php?\$query_string;
        location ~ \.php$ { root /var/www/dev/\$app; include snippets/fastcgi-php.conf; fastcgi_pass unix:$PHP_FPM_SOCK; }
    }
}
server {
    listen 80;
    server_name ~^(?<app>[^.]+)\.hml\.$BASE_DOMAIN\$;
    index index.php index.html;
    location / {
        root /var/www/hml/\$app/front;
        try_files \$uri \$uri/ /index.php?\$query_string;
        location ~ \.php$ { root /var/www/hml/\$app/front; include snippets/fastcgi-php.conf; fastcgi_pass unix:$PHP_FPM_SOCK; }
    }
    location /back/ {
        root /var/www/hml/\$app;
        try_files \$uri \$uri/ /back/index.php?\$query_string;
        location ~ \.php$ { root /var/www/hml/\$app; include snippets/fastcgi-php.conf; fastcgi_pass unix:$PHP_FPM_SOCK; }
    }
    location /api/ {
        root /var/www/hml/\$app;
        try_files \$uri \$uri/ /back/index.php?\$query_string;
        location ~ \.php$ { root /var/www/hml/\$app; include snippets/fastcgi-php.conf; fastcgi_pass unix:$PHP_FPM_SOCK; }
    }
}
server {
    listen 80;
    server_name ~^(?<app>[^.]+)\.$BASE_DOMAIN\$;
    index index.php index.html;
    location / {
        root /var/www/prd/\$app/front;
        try_files \$uri \$uri/ /index.php?\$query_string;
        location ~ \.php$ { root /var/www/prd/\$app/front; include snippets/fastcgi-php.conf; fastcgi_pass unix:$PHP_FPM_SOCK; }
    }
    location /back/ {
        root /var/www/prd/\$app;
        try_files \$uri \$uri/ /back/index.php?\$query_string;
        location ~ \.php$ { root /var/www/prd/\$app; include snippets/fastcgi-php.conf; fastcgi_pass unix:$PHP_FPM_SOCK; }
    }
    location /api/ {
        root /var/www/prd/\$app;
        try_files \$uri \$uri/ /back/index.php?\$query_string;
        location ~ \.php$ { root /var/www/prd/\$app; include snippets/fastcgi-php.conf; fastcgi_pass unix:$PHP_FPM_SOCK; }
    }
}
NGINX

ln -sf /etc/nginx/sites-available/fenor.conf /etc/nginx/sites-enabled/

# Terminal admin (ttyd global)
cat > /etc/nginx/conf.d/terminal-admin.conf << NGINX
server {
    listen 80;
    server_name terminal.$BASE_DOMAIN;
    location / {
        proxy_pass http://127.0.0.1:7681;
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host \$host;
        proxy_read_timeout 43200s;
    }
}
NGINX

nginx -t && systemctl reload nginx
ok "Nginx configurado para $BASE_DOMAIN"

# ── 8. CONFIGURAÇÃO GLOBAL ────────────────────────────
echo "  [8/8] Configuração global..."
mkdir -p /etc/fenor

cat > /etc/fenor/.env << ENV
BASE_DOMAIN=$BASE_DOMAIN
ADMIN_EMAIL=$ADMIN_EMAIL
ADMIN_PASSWORD_HASH=$ADMIN_HASH
CF_TOKEN=$CF_TOKEN
CF_ZONE_ID=$CF_ZONE_ID
CF_TUNNEL_ID=$CF_TUNNEL_ID
APPS_PATH=/var/www
TERMINAL_URL=https://terminal.$BASE_DOMAIN
DB_DRIVER=$DB_DRIVER
DB_HOST=127.0.0.1
DB_PORT=$([ "$DB_DRIVER" = "mysql" ] && echo "3306" || echo "5432")
DB_NAME=fenor
DB_USER=fenor_studio
DB_PASS=$DB_STUDIO_PASS
DB_APPS_VIEWER_PASS=$DB_APPS_VIEWER_PASS
ENV
chmod 640 /etc/fenor/.env
chown root:www-data /etc/fenor/.env
ok "Configuração salva em /etc/fenor/.env"

# ── SCRIPTS ───────────────────────────────────────────
REPO_RAW="https://raw.githubusercontent.com/FENOR-IA/fenor.ia/main"
curl -fsSL "$REPO_RAW/bin/fenor"          -o /usr/local/bin/fenor
curl -fsSL "$REPO_RAW/bin/newapp"         -o /usr/local/bin/newapp
curl -fsSL "$REPO_RAW/bin/fenor-promote"  -o /usr/local/bin/fenor-promote
curl -fsSL "$REPO_RAW/bin/save-memory"    -o /usr/local/bin/save-memory
chmod +x /usr/local/bin/fenor /usr/local/bin/newapp /usr/local/bin/fenor-promote /usr/local/bin/save-memory
ok "Scripts fenor, newapp, fenor-promote, save-memory instalados"

# ── GIT IDENTITY (para commits dos scripts) ───────────
git config --global user.email "fenor@fenor.ia"
git config --global user.name "Fenor"
git config --global init.defaultBranch dev

# ── SUDOERS (www-data → scripts fenor) ────────────────
cat > /etc/sudoers.d/fenor-scripts << 'SUDOERS'
www-data ALL=(root) NOPASSWD: /usr/local/bin/newapp
www-data ALL=(root) NOPASSWD: /usr/local/bin/fenor-promote
www-data ALL=(root) NOPASSWD: /usr/local/bin/fenor
www-data ALL=(root) NOPASSWD: /bin/systemctl restart ttyd-*
www-data ALL=(root) NOPASSWD: /usr/bin/tee /etc/fenor/ttyd.env
SUDOERS
chmod 440 /etc/sudoers.d/fenor-scripts
ok "Sudoers configurado para www-data"

# ── STUDIO ────────────────────────────────────────────
curl -fsSL "$REPO_RAW/studio/install-studio.sh" | bash &>/dev/null
# Baixa Adminer (gerenciador de banco - suporta PostgreSQL e MySQL)
curl -fsSL "https://www.adminer.org/latest.php" -o /var/www/studio/adminer.php &>/dev/null || true
ok "Studio instalado em /var/www/studio"

# ── CLOUDFLARED ───────────────────────────────────────
if ! command -v cloudflared &>/dev/null; then
  curl -fsSL https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-amd64 \
    -o /usr/local/bin/cloudflared
  chmod +x /usr/local/bin/cloudflared
fi
ok "cloudflared instalado"

# ── RESUMO ────────────────────────────────────────────
echo ""
echo "  ════════════════════════════════════════════"
echo "  Fenor instalado com sucesso!"
echo ""
echo "  Studio:   https://$BASE_DOMAIN"
echo "  Terminal: https://terminal.$BASE_DOMAIN"
echo ""
echo "  Próximos passos:"
echo "  1. Configure o Cloudflare Tunnel no painel"
echo "  2. Execute: cloudflared service install TOKEN"
echo "  3. Acesse https://$BASE_DOMAIN e faça login"
echo ""
echo "  Documentação: https://github.com/fenor-ia/fenor"
echo "  ════════════════════════════════════════════"
echo ""
