#!/bin/bash
# ═══════════════════════════════════════════════════════
# FENOR — Infrastructure Installer
# Ubuntu 24.04 LTS
# Usage: curl -fsSL https://fenor.ia.br/install.sh | bash
# ═══════════════════════════════════════════════════════

set -eE
export DEBIAN_FRONTEND=noninteractive

# ── LOG ───────────────────────────────────────────────
LOG=/var/log/fenor-install.log
mkdir -p "$(dirname "$LOG")"
echo "" >> "$LOG"
echo "=== Fenor Install: $(date '+%Y-%m-%d %H:%M:%S') ===" >> "$LOG"

# ── CORES ─────────────────────────────────────────────
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'
BLUE='\033[0;34m'; CYAN='\033[0;36m'; BOLD='\033[1m'; NC='\033[0m'

ok()   { printf "  ${GREEN}✓${NC} %s\n" "$1"; echo "[OK] $1" >> "$LOG"; }
warn() { printf "  ${YELLOW}!${NC} %s\n" "$1"; echo "[WARN] $1" >> "$LOG"; }
fail() { printf "\n  ${RED}✗${NC} %s\n\n" "$1"; echo "[FAIL] $1" >> "$LOG"; exit 1; }
step() { printf "  ${CYAN}→${NC} %s\n" "$1"; echo "[STEP] $1" >> "$LOG"; }

# ── SPINNER ───────────────────────────────────────────
_SPINNER_PID=""
spinner_start() {
  local msg="$1"
  local chars='⠋⠙⠹⠸⠼⠴⠦⠧⠇⠏'
  local i=0
  (
    while true; do
      printf "\r  ${CYAN}%s${NC} %s  " "${chars:$i:1}" "$msg"
      i=$(( (i+1) % ${#chars} ))
      sleep 0.08
    done
  ) </dev/null &
  _SPINNER_PID=$!
  disown "$_SPINNER_PID" 2>/dev/null || true
}

spinner_stop() {
  if [ -n "$_SPINNER_PID" ]; then
    kill "$_SPINNER_PID" 2>/dev/null || true
    wait "$_SPINNER_PID" 2>/dev/null || true
    _SPINNER_PID=""
    printf "\r\033[K"  # apaga linha do spinner
  fi
}

# Roda comando em background com spinner — loga tudo, mostra só o spinner
run() {
  local msg="$1"; shift
  echo "[RUN] $msg: $*" >> "$LOG"
  local t_start=$SECONDS
  spinner_start "$msg"
  local exit_code=0
  "$@" >>"$LOG" 2>&1 || exit_code=$?
  spinner_stop
  local elapsed=$(( SECONDS - t_start ))
  if [ "$exit_code" -ne 0 ]; then
    printf "  ${RED}✗${NC} %s ${RED}(falhou após %ds — veja o log)${NC}\n" "$msg" "$elapsed"
    return "$exit_code"
  fi
  printf "  ${GREEN}✓${NC} %s ${CYAN}(%ds)${NC}\n" "$msg" "$elapsed"
  echo "[OK] $msg (${elapsed}s)" >> "$LOG"
}

# ── WAIT APT LOCK ─────────────────────────────────────
wait_apt() {
  local locks=("/var/lib/dpkg/lock-frontend" "/var/lib/apt/lists/lock" "/var/cache/apt/archives/lock")
  local timeout=180 elapsed=0 waited=0
  while true; do
    local locked=0
    for lock in "${locks[@]}"; do
      if fuser "$lock" >/dev/null 2>&1; then locked=1; break; fi
    done
    [ "$locked" -eq 0 ] && break
    if [ "$waited" -eq 0 ]; then
      local holder_pid holder_name
      holder_pid=$(fuser /var/lib/dpkg/lock-frontend 2>/dev/null | awk '{print $1}')
      holder_name=$(ps -p "$holder_pid" -o comm= 2>/dev/null || echo "processo desconhecido")
      step "apt em uso por '$holder_name' (PID $holder_pid) — aguardando liberar (máx ${timeout}s)..."
      waited=1
    fi
    if [ "$elapsed" -ge "$timeout" ]; then
      fail "Timeout ${timeout}s aguardando apt. Tente: sudo kill $holder_pid"
    fi
    sleep 5; elapsed=$(( elapsed + 5 ))
  done
  if [ "$waited" -eq 1 ]; then
    step "apt liberado após ${elapsed}s, continuando..."
  fi
  return 0
}

# Wrapper apt com espera automática de lock
run_apt() {
  wait_apt
  run "$@"
}

# ── TRAP DE ERRO ──────────────────────────────────────
_CURRENT_STEP=""
trap_err() {
  local line=$1 code=$2
  spinner_stop
  echo ""
  echo -e "  ${RED}${BOLD}✗ ERRO na linha $line (código $code)${NC}"
  [ -n "$_CURRENT_STEP" ] && echo -e "  ${YELLOW}Etapa:${NC} $_CURRENT_STEP"
  echo ""
  echo -e "  ${YELLOW}Últimas linhas do log:${NC}"
  echo "  ─────────────────────────────────────────"
  tail -30 "$LOG" | sed 's/^/  /'
  echo "  ─────────────────────────────────────────"
  echo ""
  echo -e "  ${BLUE}Log completo:${NC} $LOG"
  echo ""
}
trap 'trap_err $LINENO $?' ERR

# ── BANNER ────────────────────────────────────────────
echo ""
echo "  ███████╗███████╗███╗   ██╗ ██████╗ ██████╗ "
echo "  ██╔════╝██╔════╝████╗  ██║██╔═══██╗██╔══██╗"
echo "  █████╗  █████╗  ██╔██╗ ██║██║   ██║██████╔╝"
echo "  ██╔══╝  ██╔══╝  ██║╚██╗██║██║   ██║██╔══██╗"
echo "  ██║     ███████╗██║ ╚████║╚██████╔╝██║  ██║"
echo "  ╚═╝     ╚══════╝╚═╝  ╚═══╝ ╚═════╝ ╚═╝  ╚═╝"
echo ""
echo "  Instalando infraestrutura Fenor..."
printf "  Log: ${CYAN}%s${NC}\n" "$LOG"
echo ""

# ── REQUIREMENTS ─────────────────────────────────────
[ "$(id -u)" -eq 0 ] || fail "Execute como root: sudo bash install.sh"
. /etc/os-release 2>/dev/null || true
[[ "$ID" == "ubuntu" ]] || warn "Testado no Ubuntu. Outros distros podem funcionar."

if [ -f /etc/fenor/.env ]; then
  SERVER_IP=$(curl -s --connect-timeout 5 https://api.ipify.org 2>/dev/null || hostname -I | awk '{print $1}')
  echo "  Fenor já está instalado nesta VPS."
  echo ""
  echo "  Acesso: http://$SERVER_IP"
  echo ""
  echo "  Para reinstalar do zero, remova /etc/fenor/.env e execute novamente."
  echo ""
  exit 0
fi

# ── AUTO-DETECT ───────────────────────────────────────
step "Detectando ambiente..."
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

echo ""
echo "  ┌─ Detectado ──────────────────────────────┐"
printf "  │  IP do servidor: %-25s│\n" "$SERVER_IP"
[ -n "$WEB_SERVER"  ] && printf "  │  Web server    : %-25s│\n" "$WEB_SERVER"    || printf "  │  Web server    : %-25s│\n" "instalar nginx"
[ -n "$PHP_VERSION" ] && printf "  │  PHP           : %-25s│\n" "$PHP_VERSION"   || printf "  │  PHP           : %-25s│\n" "instalar 8.2"
$HAS_NODE           && printf "  │  Node.js       : %-25s│\n" "$(node -v)"      || printf "  │  Node.js       : %-25s│\n" "instalar v20"
$HAS_CLAUDE         && printf "  │  Claude Code   : %-25s│\n" "instalado"       || printf "  │  Claude Code   : %-25s│\n" "instalar"
echo "  └──────────────────────────────────────────┘"
echo ""

INSTALL_START=$SECONDS

# ══════════════════════════════════════════════════════
# [1/8] SISTEMA
# ══════════════════════════════════════════════════════
_CURRENT_STEP="[1/8] Sistema"
echo "  ${BOLD}[1/8] Sistema${NC}"
run_apt "Atualizando lista de pacotes"  apt-get update -y
run_apt "Instalando dependências base"  apt-get install -y git curl wget unzip software-properties-common

# ══════════════════════════════════════════════════════
# [2/8] WEB SERVER + PHP
# ══════════════════════════════════════════════════════
_CURRENT_STEP="[2/8] Web server + PHP"
echo ""
echo "  ${BOLD}[2/8] Web server + PHP${NC}"

if [ -z "$WEB_SERVER" ]; then
  run_apt "Instalando Nginx" apt-get install -y nginx
  WEB_SERVER="nginx"
  systemctl enable nginx >> "$LOG" 2>&1
  systemctl start nginx  >> "$LOG" 2>&1
else
  ok "Web server existente: $WEB_SERVER (mantido)"
fi

if [ -z "$PHP_VERSION" ]; then
  step "Adicionando repositório PHP (ondrej/php)..."
  run_apt "add-apt-repository php" add-apt-repository -y ppa:ondrej/php
  run_apt "Atualizando pacotes após novo repositório" apt-get update -y
  run_apt "Instalando PHP 8.2 + extensões" \
    apt-get install -y php8.2-fpm php8.2-pgsql php8.2-curl php8.2-mbstring php8.2-xml
  PHP_VERSION="8.2"
  PHP_FPM_SOCK="/run/php/php8.2-fpm.sock"
  systemctl enable php8.2-fpm >> "$LOG" 2>&1
  systemctl start  php8.2-fpm >> "$LOG" 2>&1
else
  run_apt "Instalando extensões PHP $PHP_VERSION" \
    apt-get install -y php${PHP_VERSION}-pgsql php${PHP_VERSION}-curl php${PHP_VERSION}-mbstring php${PHP_VERSION}-xml
  systemctl enable php${PHP_VERSION}-fpm >> "$LOG" 2>&1 || true
  systemctl start  php${PHP_VERSION}-fpm >> "$LOG" 2>&1 || true
  for sock in "/run/php/php${PHP_VERSION}-fpm.sock" "/var/run/php/php${PHP_VERSION}-fpm.sock"; do
    [ -S "$sock" ] && PHP_FPM_SOCK="$sock" && break
  done
  ok "PHP $PHP_VERSION — socket: $PHP_FPM_SOCK"
fi

if ! id postgres &>/dev/null; then
  run_apt "Instalando PostgreSQL" apt-get install -y postgresql postgresql-contrib
  systemctl enable postgresql >> "$LOG" 2>&1
  systemctl start  postgresql >> "$LOG" 2>&1
else
  systemctl enable postgresql >> "$LOG" 2>&1 || true
  systemctl start  postgresql >> "$LOG" 2>&1 || true
  ok "PostgreSQL encontrado (mantido)"
fi

# Hash de senha (PHP já disponível)
ADMIN_HASH=$(php -r "echo password_hash('$ADMIN_PASS', PASSWORD_BCRYPT);" 2>/dev/null || echo "")
[ -z "$ADMIN_HASH" ] && fail "Não foi possível gerar hash de senha."

# ══════════════════════════════════════════════════════
# [3/8] NODE.JS + CLAUDE CODE
# ══════════════════════════════════════════════════════
_CURRENT_STEP="[3/8] Node.js + Claude Code"
echo ""
echo "  ${BOLD}[3/8] Node.js + Claude Code${NC}"

if ! $HAS_NODE; then
  step "Configurando repositório NodeSource v20..."
  # nodesource setup emite avisos de qemu/hypervisor — são apenas informativos
  run "Configurando repositório Node.js v20" \
    bash -c "curl -fsSL https://deb.nodesource.com/setup_20.x | bash -"
  run_apt "Instalando Node.js" apt-get install -y nodejs
  ok "Node.js $(node -v) instalado"
else
  ok "Node.js já presente: $(node -v)"
fi

if ! $HAS_CLAUDE; then
  step "Instalando Claude Code via npm (pode levar 2-3 minutos)..."
  run "npm install -g @anthropic-ai/claude-code" \
    npm install -g @anthropic-ai/claude-code
  ok "Claude Code instalado: $(claude --version 2>/dev/null | head -1)"
else
  ok "Claude Code já presente: $(claude --version 2>/dev/null | head -1)"
fi

# Garante symlink do binário claude
CLAUDE_BIN=$(which claude 2>/dev/null || true)
if [ -n "$CLAUDE_BIN" ]; then
  CLAUDE_REAL=$(readlink -f "$CLAUDE_BIN" 2>/dev/null || echo "$CLAUDE_BIN")
  if [ "$CLAUDE_REAL" != "/usr/local/bin/claude" ] && [ ! -f "/opt/claude" ]; then
    cp "$CLAUDE_REAL" /opt/claude 2>/dev/null || true
    chmod 755 /opt/claude 2>/dev/null || true
    ln -sf /opt/claude /usr/local/bin/claude 2>/dev/null || true
  fi
  chmod 755 "$CLAUDE_BIN" 2>/dev/null || true
fi

# ══════════════════════════════════════════════════════
# [4/8] TTYD
# ══════════════════════════════════════════════════════
_CURRENT_STEP="[4/8] Terminal web (ttyd)"
echo ""
echo "  ${BOLD}[4/8] Terminal web (ttyd)${NC}"

if ! command -v ttyd &>/dev/null; then
  ARCH=$(uname -m)
  [ "$ARCH" = "x86_64" ] && TTYD_BIN="ttyd.x86_64" || TTYD_BIN="ttyd.aarch64"
  step "Baixando ttyd ($ARCH)..."
  run "Download ttyd 1.7.7" \
    wget -q "https://github.com/tsl0922/ttyd/releases/download/1.7.7/$TTYD_BIN" -O /usr/local/bin/ttyd
  chmod +x /usr/local/bin/ttyd
else
  ok "ttyd já presente"
fi

id fenor &>/dev/null || useradd -m -s /bin/bash fenor

if ! systemctl is-active ttyd &>/dev/null; then
  step "Criando serviço systemd ttyd..."
  cat > /etc/systemd/system/ttyd.service << EOF
[Unit]
Description=ttyd web terminal - Fenor
After=network.target

[Service]
User=fenor
ExecStart=/usr/local/bin/ttyd --port 7681 --interface 127.0.0.1 --writable bash
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
EOF
  systemctl daemon-reload >> "$LOG" 2>&1
  systemctl enable ttyd   >> "$LOG" 2>&1
  systemctl start  ttyd   >> "$LOG" 2>&1
fi
ok "ttyd ativo em 127.0.0.1:7681"

# ══════════════════════════════════════════════════════
# [5/8] BANCO DE DADOS
# ══════════════════════════════════════════════════════
_CURRENT_STEP="[5/8] Banco de dados (PostgreSQL)"
echo ""
echo "  ${BOLD}[5/8] Banco de dados${NC}"
step "Criando database, usuário e tabelas..."

su - postgres -c "psql -c \"CREATE DATABASE fenor;\"" >> "$LOG" 2>&1 || true
su - postgres -c "psql -c \"CREATE USER fenor_studio WITH PASSWORD '$DB_STUDIO_PASS';\"" >> "$LOG" 2>&1 || true
su - postgres -c "psql -c \"GRANT ALL PRIVILEGES ON DATABASE fenor TO fenor_studio;\"" >> "$LOG" 2>&1 || true

su - postgres -c "psql -d fenor" >> "$LOG" 2>&1 <<SQL
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
    language     VARCHAR(5)   NOT NULL DEFAULT 'pt',
    status       VARCHAR(20)  NOT NULL DEFAULT 'registered',
    config       JSONB,
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
ok "Database fenor + tabelas criadas"

# ══════════════════════════════════════════════════════
# [6/8] DIRETÓRIOS
# ══════════════════════════════════════════════════════
_CURRENT_STEP="[6/8] Diretórios"
echo ""
echo "  ${BOLD}[6/8] Diretórios${NC}"
mkdir -p /var/www/{dev,hml,prd,studio}
chown -R fenor:www-data /var/www/
chmod -R 775 /var/www/
mkdir -p /etc/fenor/keys
chown root:www-data /etc/fenor/keys
chmod 750 /etc/fenor/keys
ok "Diretórios criados"

# ══════════════════════════════════════════════════════
# [7/8] NGINX
# ══════════════════════════════════════════════════════
_CURRENT_STEP="[7/8] Nginx"
echo ""
echo "  ${BOLD}[7/8] Nginx${NC}"
step "Configurando virtual hosts..."
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

nginx -t >> "$LOG" 2>&1 && systemctl reload nginx >> "$LOG" 2>&1
ok "Nginx configurado"

# ══════════════════════════════════════════════════════
# [8/8] CONFIGURAÇÃO GLOBAL
# ══════════════════════════════════════════════════════
_CURRENT_STEP="[8/8] Configuração global"
echo ""
echo "  ${BOLD}[8/8] Configuração global${NC}"
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
ok "Config salva em /etc/fenor/.env"

step "Baixando scripts CLI..."
REPO_RAW="https://raw.githubusercontent.com/FENOR-IA/fenor.ia/main"
run "Download: fenor"          curl -fsSL "$REPO_RAW/bin/fenor"          -o /usr/local/bin/fenor
run "Download: newapp"         curl -fsSL "$REPO_RAW/bin/newapp"         -o /usr/local/bin/newapp
run "Download: fenor-promote"  curl -fsSL "$REPO_RAW/bin/fenor-promote"  -o /usr/local/bin/fenor-promote
run "Download: fenor-git"      curl -fsSL "$REPO_RAW/bin/fenor-git"      -o /usr/local/bin/fenor-git
run "Download: fenor-agent"    curl -fsSL "$REPO_RAW/bin/fenor-agent"    -o /usr/local/bin/fenor-agent
run "Download: fenor-learn"    curl -fsSL "$REPO_RAW/bin/fenor-learn"    -o /usr/local/bin/fenor-learn
run "Download: fenor-session"  curl -fsSL "$REPO_RAW/bin/fenor-session"  -o /usr/local/bin/fenor-session
run "Download: fenor-terminal" curl -fsSL "$REPO_RAW/bin/fenor-terminal" -o /usr/local/bin/fenor-terminal
run "Download: save-memory"    curl -fsSL "$REPO_RAW/bin/save-memory"    -o /usr/local/bin/save-memory
chmod +x /usr/local/bin/fenor /usr/local/bin/newapp /usr/local/bin/fenor-promote \
         /usr/local/bin/fenor-git /usr/local/bin/fenor-agent /usr/local/bin/fenor-learn \
         /usr/local/bin/fenor-session /usr/local/bin/fenor-terminal /usr/local/bin/save-memory
ok "Scripts instalados"

step "Clonando templates..."
run "Clone fenor-ia-template" \
  bash -c "rm -rf /etc/fenor/templates \
    && git clone --depth=1 \
       'https://github.com/FENOR-IA/fenor-ia-template.git' /etc/fenor/templates"
ok "Templates: /etc/fenor/templates/"

step "Configurando git global..."
git config --global user.email "fenor@fenor.ia"
git config --global user.name "Fenor"
git config --global init.defaultBranch dev
ok "Git configurado"

step "Configurando sudoers..."
cat > /etc/sudoers.d/fenor-scripts << 'SUDOERS'
www-data ALL=(root) NOPASSWD: /usr/local/bin/newapp
www-data ALL=(root) NOPASSWD: /usr/local/bin/fenor-promote
www-data ALL=(root) NOPASSWD: /usr/local/bin/fenor
www-data ALL=(root) NOPASSWD: /usr/local/bin/fenor-git
www-data ALL=(root) NOPASSWD: /bin/systemctl restart ttyd-*
www-data ALL=(root) NOPASSWD: /usr/bin/tee /etc/fenor/ttyd.env
SUDOERS
chmod 440 /etc/sudoers.d/fenor-scripts
ok "Sudoers configurado"

step "Instalando Studio..."
run "install-studio.sh" bash -c "curl -fsSL '$REPO_RAW/studio/install-studio.sh' | bash"
run "Adminer" bash -c "curl -fsSL 'https://www.adminer.org/latest.php' -o /var/www/studio/adminer.php" || warn "Adminer não instalado (opcional)"
ok "Studio instalado"

step "Salvando versão instalada..."
curl -fsSL "$REPO_RAW/VERSION" -o /etc/fenor/version 2>/dev/null || echo "1.0.0" > /etc/fenor/version
ok "Versão: $(cat /etc/fenor/version)"

step "Instalando cloudflared..."
if ! command -v cloudflared &>/dev/null; then
  run "Download cloudflared" \
    curl -fsSL https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-amd64 \
      -o /usr/local/bin/cloudflared
  chmod +x /usr/local/bin/cloudflared
fi
ok "cloudflared instalado"

# ══════════════════════════════════════════════════════
# RESUMO
# ══════════════════════════════════════════════════════
TOTAL=$(( SECONDS - INSTALL_START ))
echo ""
echo "  ╔══════════════════════════════════════════╗"
echo "  ║     Fenor instalado com sucesso! 🎉      ║"
echo "  ╠══════════════════════════════════════════╣"
echo "  ║                                          ║"
printf "  ║  Acesso:   http://%-24s║\n" "$SERVER_IP"
printf "  ║  Terminal: http://%s/terminal/\n" "$SERVER_IP"
echo "  ║                                          ║"
echo "  ║  Login:    admin@fenor.local             ║"
printf "  ║  Senha:    %-32s║\n" "$ADMIN_PASS"
echo "  ║                                          ║"
echo "  ║  Troque a senha em Studio → Settings     ║"
echo "  ║  após o primeiro login.                  ║"
echo "  ║                                          ║"
echo "  ╠══════════════════════════════════════════╣"
echo "  ║  GitHub (opcional):                      ║"
echo "  ║  Studio → Settings → GitHub              ║"
echo "  ║  Cole um Personal Access Token           ║"
echo "  ║  (escopos: repo, read:org)               ║"
echo "  ║                                          ║"
printf "  ╠══════════════════════════════════════════╣\n"
printf "  ║  Tempo total: %-28s║\n" "${TOTAL}s"
printf "  ║  Log completo: %-27s║\n" "$LOG"
echo "  ╚══════════════════════════════════════════╝"
echo ""
