#!/bin/bash
# ═══════════════════════════════════════════════════════
# FENOR — Uninstaller
# Remove a instalação do Fenor para permitir reinstalação
# Usage: sudo bash uninstall.sh
# ═══════════════════════════════════════════════════════

set -eE

LOG=/var/log/fenor-uninstall.log
mkdir -p "$(dirname "$LOG")"
echo "" >> "$LOG"
echo "=== Fenor Uninstall: $(date '+%Y-%m-%d %H:%M:%S') ===" >> "$LOG"

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'
CYAN='\033[0;36m'; BOLD='\033[1m'; NC='\033[0m'

ok()   { printf "  ${GREEN}✓${NC} %s\n" "$1"; echo "[OK] $1"   >> "$LOG"; }
warn() { printf "  ${YELLOW}!${NC} %s\n" "$1"; echo "[WARN] $1" >> "$LOG"; }
step() { printf "  ${CYAN}→${NC} %s\n" "$1"; echo "[STEP] $1"  >> "$LOG"; }

[ "$(id -u)" -eq 0 ] || { echo -e "  ${RED}Execute como root: sudo bash uninstall.sh${NC}"; exit 1; }

echo ""
echo "  ███████╗███████╗███╗   ██╗ ██████╗ ██████╗ "
echo "  ██╔════╝██╔════╝████╗  ██║██╔═══██╗██╔══██╗"
echo "  █████╗  █████╗  ██╔██╗ ██║██║   ██║██████╔╝"
echo "  ██╔══╝  ██╔══╝  ██║╚██╗██║██║   ██║██╔══██╗"
echo "  ██║     ███████╗██║ ╚████║╚██████╔╝██║  ██║"
echo "  ╚═╝     ╚══════╝╚═╝  ╚═══╝ ╚═════╝ ╚═╝  ╚═╝"
echo ""
echo -e "  ${YELLOW}${BOLD}ATENÇÃO: Este script remove a instalação do Fenor.${NC}"
echo ""
echo "  O que será removido:"
echo "    • Banco de dados PostgreSQL (fenor + usuários)"
echo "    • Config Nginx do Fenor"
echo "    • Serviço ttyd"
echo "    • Scripts CLI (newapp, fenor, fenor-git, ...)"
echo "    • Diretório /etc/fenor/ (config + boilerplate)"
echo "    • Arquivos do Studio (/var/www/studio/)"
echo "    • Sudoers do Fenor"
echo ""
echo "  O que será preservado:"
echo "    • Apps em /var/www/{dev,hml,prd}/"
echo "    • Nginx, PHP, PostgreSQL, Node.js (pacotes do sistema)"
echo "    • Cloudflared"
echo ""

# ── CONFIRMAÇÃO ───────────────────────────────────────
printf "  Digite ${BOLD}REMOVER${NC} para confirmar: "
read -r CONFIRM
if [ "$CONFIRM" != "REMOVER" ]; then
  echo ""
  echo "  Cancelado."
  echo ""
  exit 0
fi
echo ""

START=$SECONDS

# ── [1/6] STUDIO ──────────────────────────────────────
echo "  ${BOLD}[1/6] Studio${NC}"
step "Removendo arquivos do Studio..."
rm -rf /var/www/studio/
ok "Studio removido"

# ── [2/6] NGINX ───────────────────────────────────────
echo ""
echo "  ${BOLD}[2/6] Nginx${NC}"
step "Removendo config fenor.conf..."
rm -f /etc/nginx/sites-enabled/fenor.conf
rm -f /etc/nginx/sites-available/fenor.conf
if nginx -t >>"$LOG" 2>&1; then
  systemctl reload nginx >>"$LOG" 2>&1 && ok "Nginx recarregado" || warn "Nginx reload falhou"
else
  warn "Config Nginx inválida após remoção — verifique manualmente"
fi

# ── [3/6] TTYD ────────────────────────────────────────
echo ""
echo "  ${BOLD}[3/6] Terminal (ttyd)${NC}"
step "Parando e removendo serviço ttyd..."
systemctl stop ttyd    >>"$LOG" 2>&1 || true
systemctl disable ttyd >>"$LOG" 2>&1 || true
rm -f /etc/systemd/system/ttyd.service
systemctl daemon-reload >>"$LOG" 2>&1 || true
rm -f /usr/local/bin/ttyd
ok "ttyd removido"

# ── [4/6] SCRIPTS + CONFIG ────────────────────────────
echo ""
echo "  ${BOLD}[4/6] Scripts e configuração${NC}"
step "Removendo scripts CLI..."
rm -f /usr/local/bin/fenor
rm -f /usr/local/bin/newapp
rm -f /usr/local/bin/fenor-promote
rm -f /usr/local/bin/fenor-git
rm -f /usr/local/bin/save-memory
ok "Scripts removidos"

step "Removendo /etc/fenor/..."
rm -rf /etc/fenor/
ok "/etc/fenor/ removido"

step "Removendo sudoers..."
rm -f /etc/sudoers.d/fenor-scripts
ok "Sudoers removido"

# ── [5/6] BANCO DE DADOS ──────────────────────────────
echo ""
echo "  ${BOLD}[5/6] Banco de dados PostgreSQL${NC}"
step "Dropando banco fenor e usuários..."
su - postgres -c "psql -c 'DROP DATABASE IF EXISTS fenor;'"         >>"$LOG" 2>&1 && ok "Database fenor removida"     || warn "Falhou ao dropar database fenor"
su - postgres -c "psql -c 'DROP USER IF EXISTS fenor_studio;'"      >>"$LOG" 2>&1 && ok "Usuário fenor_studio removido"     || warn "Falhou ao dropar fenor_studio"
su - postgres -c "psql -c 'DROP USER IF EXISTS fenor_apps_viewer;'" >>"$LOG" 2>&1 && ok "Usuário fenor_apps_viewer removido" || warn "Falhou ao dropar fenor_apps_viewer"

# ── [6/6] USUÁRIO DO SISTEMA ──────────────────────────
echo ""
echo "  ${BOLD}[6/6] Usuário do sistema${NC}"
step "Removendo usuário fenor..."
if id fenor &>/dev/null; then
  userdel -r fenor >>"$LOG" 2>&1 && ok "Usuário fenor removido" || warn "Falhou ao remover usuário fenor"
else
  ok "Usuário fenor não existe, pulando"
fi

# ── CONCLUSÃO ─────────────────────────────────────────
ELAPSED=$(( SECONDS - START ))
echo ""
echo "  ─────────────────────────────────────────────"
echo -e "  ${GREEN}${BOLD}✓ Fenor desinstalado em ${ELAPSED}s${NC}"
echo ""
echo "  Apps preservados em /var/www/{dev,hml,prd}/"
echo ""
echo "  Para reinstalar:"
echo "    curl -fsSL https://fenor.ia.br/install.sh | bash"
echo ""
