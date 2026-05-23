#!/bin/bash
# ═══════════════════════════════════════════════════════
# FENOR — Instalação da Infraestrutura
# Ubuntu 24.04 LTS
# Uso: curl -fsSL https://fenor.ia.br/install.sh | bash
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

# ── VALORES AUTOMÁTICOS ───────────────────────────────
SERVER_IP=$(curl -s --connect-timeout 5 https://api.ipify.org 2>/dev/null \
  || curl -s --connect-timeout 5 https://ifconfig.me 2>/dev/null \
  || hostname -I | awk '{print $1}')

BASE_DOMAIN="$SERVER_IP"
ADMIN_EMAIL="admin@fenor.local"
ADMIN_PASS=$(openssl rand -base64 16 | tr -dc 'a-zA-Z0-9' | head -c 14)
CF_TOKEN=""
CF_ZONE_ID=""
CF_TUNNEL_ID=""
DB_DRIVER="pgsql"
DB_STUDIO_PASS=$(openssl rand -base64 16 | tr -dc 'a-zA-Z0-9' | head -c 20)
DB_APPS_VIEWER_PASS=$(openssl rand -base64 16 | tr -dc 'a-zA-Z0-9' | head -c 20)

# ── DETECÇÃO DO AMBIENTE ──────────────────────────────
WEB_SERVER=""
command -v nginx   &>/dev/null && WEB_SERVER="nginx"
command -v apache2 &>/dev/null && [ -z "$WEB_SERVER" ] && WEB_SERVER="apache2"

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

HAS_NODE=false;   command -v node   &>/dev/null && HAS_NODE=true
HAS_CLAUDE=false; command -v claude &>/dev/null && HAS_CLAUDE=true

echo "  ┌─ Detectado ──────────────────────────────┐"
echo "  │  IP servidor: $SERVER_IP"
[ -n "$WEB_SERVER"  ] && echo "  │  Web server : $WEB_SERVER"    || echo "  │  Web server : instalar nginx"
[ -n "$PHP_VERSION" ] && echo "  │  PHP        : $PHP_VERSION"   || echo "  │  PHP        : instalar 8.2"
$HAS_NODE           && echo "  │  Node.js    : $(node -v)"       || echo "  │  Node.js    : instalar v20"
$HAS_CLAUDE         && echo "  │  Claude     : $(claude --version 2>/dev/null | head -1)" || echo "  │  Claude     : instalar"
echo "  └──────────────────────────────────────────┘"
echo ""

# ── 1. SISTEMA ────────────────────────────────────────
echo "  [1/8] Sistema..."
DEBIAN_FRONTEND=noninteractive apt update -qq
DEBIAN_FRONTEND=noninteractive apt install -y -qq git curl wget unzip software-properties-common
ok "Dependências básicas"

# ── 2. WEB SERVER + PHP ───────────────────────────────
echo "  [2/8] Web server + PHP..."

if [ -z "$WEB_SERVER" ]; then
  DEBIAN_FRONTEND=noninteractive apt install -y -qq nginx
  WEB_SERVER="nginx"
  systemctl enable nginx &>/dev/null
  systemctl start nginx
  ok "Nginx instalado"
else
  ok "Web server existente: $WEB_SERVER (mantido)"
fi

if [ -z "$PHP_VERSION" ]; then
  add-apt-repository -y ppa:ondrej/php &>/dev/null
  apt update -qq
  DEBIAN_FRONTEND=noninteractive apt install -y -qq php8.2-fpm php8.2-pgsql php8.2-curl php8.2-mbstring php8.2-xml
  PHP_VERSION="8.2"
  PHP_FPM_SOCK="/run/php/php8.2-fpm.sock"
  systemctl enable php8.2-fpm &>/dev/null
  systemctl start php8.2-fpm
  ok "PHP 8.2 instalado"
else
  DEBIAN_FRONTEND=noninteractive apt install -y -qq php${PHP_VERSION}-pgsql php${PHP_VERSION}-curl php${PHP_VERSION}-mbstring php${PHP_VERSION}-xml &>/dev/null || true
  systemctl enable php${PHP_VERSION}-fpm &>/dev/null || true
  systemctl start  php${PHP_VERSION}-fpm 2>/dev/null || true
  for sock in "/run/php/php${PHP_VERSION}-fpm.sock" "/var/run/php/php${PHP_VERSION}-fpm.sock"; do
    [ -S "$sock" ] && PHP_FPM_SOCK="$sock" && break
  done
  ok "PHP $PHP_VERSION existente — socket: $PHP_FPM_SOCK"
fi

# PostgreSQL
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

# ── GERA HASH DA SENHA (PHP disponível agora) ─────────
ADMIN_HASH=$(php -r "echo password_hash('$ADMIN_PASS', PASSWORD_BCRYPT);" 2>/dev/null || echo "")
[ -z "$ADMIN_HASH" ] && fail "Não foi possível gerar hash da senha."

# ── 3. NODE.JS + CLAUDE CODE ──────────────────────────
echo "  [3/8] Node.js + Claude Code..."
if ! $HAS_NODE; then
  curl -fsSL https://deb.nodesource.com/setup_20.x | bash - &>/dev/null
  DEBIAN_FRONTEND=noninteractive apt install -y -qq nodejs
  ok "Node.js $(node -v) instalado"
else
  ok "Node.js existente: $(node -v)"
fi
if ! $HAS_CLAUDE; then
  npm install -g @anthropic-ai/claude-code &>/dev/null
  ok "Claude Code instalado"
else
  ok "Claude Code existente"
fi
CLAUDE_BIN=$(which claude 2>/dev/null)
if [ -n "$CLAUDE_BIN" ]; then
  CLAUDE_REAL=$(readlink -f "$CLAUDE_BIN" 2>/dev/null || echo "$CLAUDE_BIN")
  if [ "$CLAUDE_REAL" != "/usr/local/bin/claude" ] && [ ! -f "/opt/claude" ]; then
    cp "$CLAUDE_REAL" /opt/claude 2>/dev/null || true
    chmod 755 /opt/claude 2>/dev/null || true
    ln -sf /opt/claude /usr/local/bin/claude 2>/dev/null || true
  fi
  chmod 755 "$CLAUDE_BIN" 2>/dev/null || true
fi

# ── 4. TTYD ───────────────────────────────────────────
echo "  [4/8] Terminal web (ttyd)..."
if ! command -v ttyd &>/dev/null; then
  ARCH=$(uname -m)
  [ "$ARCH" = "x86_64" ] && TTYD_BIN="ttyd.x86_64" || TTYD_BIN="ttyd.aarch64"
  wget -q "https://github.com/tsl0922/ttyd/releases/download/1.7.7/$TTYD_BIN" -O /usr/local/bin/ttyd
  chmod +x /usr/local/bin/ttyd
  ok "ttyd instalado"
else
  ok "ttyd existente"
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
ok "ttyd ativo (127.0.0.1:7681)"

# ── 5. BANCO DE DADOS ─────────────────────────────────
echo "  [5/8] Banco de dados..."
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
    ('TERMINAL_URL','http://$SERVER_IP/terminal/'),
    ('CF_TOKEN',''),('CF_ZONE_ID',''),('CF_TUNNEL_ID',''),
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

# ── 6. DIRETÓRIOS ─────────────────────────────────────
echo "  [6/8] Diretórios..."
mkdir -p /var/www/{dev,hml,prd,studio}
chown -R fenor:www-data /var/www/
chmod -R 775 /var/www/
mkdir -p /etc/fenor/keys
chown root:www-data /etc/fenor/keys
chmod 750 /etc/fenor/keys
ok "Diretórios criados"

# ── 7. NGINX ──────────────────────────────────────────
echo "  [7/8] Nginx..."
rm -f /etc/nginx/sites-enabled/default

cat > /etc/nginx/sites-available/fenor.conf << NGINX
server {
    listen 80 default_server;
    server_name _;
    root /var/www/studio;
    index login.php dashboard.php index.html;

    location /terminal/ {
        proxy_pass http://127.0.0.1:7681/;
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host \$host;
        proxy_read_timeout 43200s;
    }
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
rm -f /etc/nginx/conf.d/terminal-admin.conf

nginx -t && systemctl reload nginx
ok "Nginx configurado"

# ── 8. CONFIGURAÇÃO GLOBAL ────────────────────────────
echo "  [8/8] Configuração global..."
mkdir -p /etc/fenor

cat > /etc/fenor/.env << ENV
BASE_DOMAIN=$BASE_DOMAIN
ADMIN_EMAIL=$ADMIN_EMAIL
ADMIN_PASSWORD_HASH=$ADMIN_HASH
CF_TOKEN=
CF_ZONE_ID=
CF_TUNNEL_ID=
APPS_PATH=/var/www
TERMINAL_URL=http://$SERVER_IP/terminal/
DB_DRIVER=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
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
ok "Scripts instalados"

# ── GIT IDENTITY ──────────────────────────────────────
git config --global user.email "fenor@fenor.ia"
git config --global user.name "Fenor"
git config --global init.defaultBranch dev

# ── SUDOERS ───────────────────────────────────────────
cat > /etc/sudoers.d/fenor-scripts << 'SUDOERS'
www-data ALL=(root) NOPASSWD: /usr/local/bin/newapp
www-data ALL=(root) NOPASSWD: /usr/local/bin/fenor-promote
www-data ALL=(root) NOPASSWD: /usr/local/bin/fenor
www-data ALL=(root) NOPASSWD: /bin/systemctl restart ttyd-*
www-data ALL=(root) NOPASSWD: /usr/bin/tee /etc/fenor/ttyd.env
SUDOERS
chmod 440 /etc/sudoers.d/fenor-scripts
ok "Sudoers configurado"

# ── STUDIO ────────────────────────────────────────────
curl -fsSL "$REPO_RAW/studio/install-studio.sh" | bash &>/dev/null
curl -fsSL "https://www.adminer.org/latest.php" -o /var/www/studio/adminer.php &>/dev/null || true
ok "Studio instalado"

# ── CLOUDFLARED ───────────────────────────────────────
if ! command -v cloudflared &>/dev/null; then
  curl -fsSL https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-amd64 \
    -o /usr/local/bin/cloudflared
  chmod +x /usr/local/bin/cloudflared
fi
ok "cloudflared instalado"

# ── RESUMO ────────────────────────────────────────────
echo ""
echo "  ╔══════════════════════════════════════════╗"
echo "  ║        Fenor instalado com sucesso!      ║"
echo "  ╠══════════════════════════════════════════╣"
echo "  ║                                          ║"
printf "  ║  Acesso:  http://%-24s║\n" "$SERVER_IP"
printf "  ║  Terminal: http://%s/terminal/\n" "$SERVER_IP"
echo "  ║                                          ║"
echo "  ║  Login:  admin@fenor.local               ║"
printf "  ║  Senha:  %-32s║\n" "$ADMIN_PASS"
echo "  ║                                          ║"
echo "  ║  Troque a senha nas Configurações        ║"
echo "  ║  do Studio após o primeiro acesso.       ║"
echo "  ║                                          ║"
echo "  ╚══════════════════════════════════════════╝"
echo ""
