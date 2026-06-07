#!/bin/bash
# ═══════════════════════════════════════════════════════
# FENOR — Updater
# Usage: curl -fsSL https://fenor.ia.br/update.sh | bash
# ═══════════════════════════════════════════════════════

set -eE
export DEBIAN_FRONTEND=noninteractive

LOG=/var/log/fenor-update.log
mkdir -p "$(dirname "$LOG")"
echo "" >> "$LOG"
echo "=== Fenor Update: $(date '+%Y-%m-%d %H:%M:%S') ===" >> "$LOG"

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'
BLUE='\033[0;34m'; CYAN='\033[0;36m'; BOLD='\033[1m'; NC='\033[0m'

ok()   { printf "  ${GREEN}✓${NC} %s\n" "$1"; echo "[OK] $1" >> "$LOG"; }
warn() { printf "  ${YELLOW}!${NC} %s\n" "$1"; echo "[WARN] $1" >> "$LOG"; }
fail() { printf "\n  ${RED}✗${NC} %s\n\n" "$1"; echo "[FAIL] $1" >> "$LOG"; exit 1; }
step() { printf "  ${CYAN}→${NC} %s\n" "$1"; echo "[STEP] $1" >> "$LOG"; }

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
    printf "\r\033[K"
  fi
}

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
    printf "  ${RED}✗${NC} %s ${RED}(falhou após %ds)${NC}\n" "$msg" "$elapsed"
    return "$exit_code"
  fi
  printf "  ${GREEN}✓${NC} %s ${CYAN}(%ds)${NC}\n" "$msg" "$elapsed"
  echo "[OK] $msg (${elapsed}s)" >> "$LOG"
}

trap_err() {
  spinner_stop
  echo -e "\n  ${RED}${BOLD}✗ ERRO (linha $1, código $2)${NC}"
  echo -e "  ${YELLOW}Últimas linhas do log:${NC}"
  tail -20 "$LOG" | sed 's/^/  /'
  echo -e "\n  ${BLUE}Log completo:${NC} $LOG\n"
}
trap 'trap_err $LINENO $?' ERR

# ── REQUIREMENTS ──────────────────────────────────────
[ "$(id -u)" -eq 0 ] || fail "Execute como root: sudo bash update.sh"
[ -f /etc/fenor/.env ] || fail "Fenor não está instalado. Execute: curl -fsSL https://fenor.ia.br/install.sh | bash"

REPO_RAW="https://raw.githubusercontent.com/FENOR-IA/fenor.ia/main"
TEMPLATES_DIR="/etc/fenor/templates"
UPDATE_START=$SECONDS

# Versão instalada atual
INSTALLED_VERSION=$(cat /etc/fenor/version 2>/dev/null || echo "desconhecida")
# Versão disponível no repo
NEW_VERSION=$(curl -fsSL "$REPO_RAW/VERSION" 2>/dev/null | tr -d '[:space:]' || echo "?")

echo ""
echo "  ███████╗███████╗███╗   ██╗ ██████╗ ██████╗ "
echo "  ██╔════╝██╔════╝████╗  ██║██╔═══██╗██╔══██╗"
echo "  █████╗  █████╗  ██╔██╗ ██║██║   ██║██████╔╝"
echo "  ██╔══╝  ██╔══╝  ██║╚██╗██║██║   ██║██╔══██╗"
echo "  ██║     ███████╗██║ ╚████║╚██████╔╝██║  ██║"
echo "  ╚═╝     ╚══════╝╚═╝  ╚═══╝ ╚═════╝ ╚═╝  ╚═╝"
echo ""
printf "  Instalado: ${CYAN}%s${NC}   Disponível: ${GREEN}%s${NC}\n" "$INSTALLED_VERSION" "$NEW_VERSION"
printf "  Log: ${CYAN}%s${NC}\n" "$LOG"
echo ""

# ══════════════════════════════════════════════════════
# [1/3] SCRIPTS CLI
# ══════════════════════════════════════════════════════
echo "  ${BOLD}[1/3] Scripts CLI${NC}"
run "fenor"          curl -fsSL "$REPO_RAW/bin/fenor"          -o /usr/local/bin/fenor
run "newapp"         curl -fsSL "$REPO_RAW/bin/newapp"         -o /usr/local/bin/newapp
run "fenor-promote"  curl -fsSL "$REPO_RAW/bin/fenor-promote"  -o /usr/local/bin/fenor-promote
run "fenor-git"      curl -fsSL "$REPO_RAW/bin/fenor-git"      -o /usr/local/bin/fenor-git
run "fenor-agent"    curl -fsSL "$REPO_RAW/bin/fenor-agent"    -o /usr/local/bin/fenor-agent
run "fenor-learn"    curl -fsSL "$REPO_RAW/bin/fenor-learn"    -o /usr/local/bin/fenor-learn
run "fenor-session"  curl -fsSL "$REPO_RAW/bin/fenor-session"  -o /usr/local/bin/fenor-session
run "save-memory"    curl -fsSL "$REPO_RAW/bin/save-memory"    -o /usr/local/bin/save-memory
chmod +x /usr/local/bin/fenor /usr/local/bin/newapp /usr/local/bin/fenor-promote \
         /usr/local/bin/fenor-git /usr/local/bin/fenor-agent /usr/local/bin/fenor-learn \
         /usr/local/bin/fenor-session /usr/local/bin/save-memory
ok "Scripts atualizados"

# ══════════════════════════════════════════════════════
# [2/3] STUDIO
# ══════════════════════════════════════════════════════
echo ""
echo "  ${BOLD}[2/3] Studio${NC}"
run "Atualizando studio" \
  bash -c "curl -fsSL '$REPO_RAW/studio/install-studio.sh' | bash"
ok "Studio atualizado"

# ══════════════════════════════════════════════════════
# [3/3] TEMPLATES OFICIAIS
# ══════════════════════════════════════════════════════
echo ""
echo "  ${BOLD}[3/3] Templates${NC}"
if [ -d "$TEMPLATES_DIR/.git" ]; then
  run "git pull — fenor-ia-template" git -C "$TEMPLATES_DIR" pull --ff-only
else
  run "Clonando fenor-ia-template" \
    git clone --depth=1 https://github.com/FENOR-IA/fenor-ia-template.git "$TEMPLATES_DIR"
fi

# Lista templates e versões após update
echo ""
step "Templates instalados:"
if [ -f "$TEMPLATES_DIR/index.json" ]; then
  python3 -c "
import json
with open('$TEMPLATES_DIR/index.json') as f:
    for t in json.load(f):
        print(f\"    {t['name']:<12} v{t.get('version','?'):<8} {t.get('description','')}\")
" 2>/dev/null || grep -o '"name":"[^"]*"\|"version":"[^"]*"' "$TEMPLATES_DIR/index.json" | paste - -
fi

# Salva versão instalada
echo "$NEW_VERSION" > /etc/fenor/version

# ══════════════════════════════════════════════════════
# RESUMO
# ══════════════════════════════════════════════════════
TOTAL=$(( SECONDS - UPDATE_START ))
echo ""
echo "  ╔══════════════════════════════════════════╗"
echo "  ║       Fenor atualizado com sucesso!      ║"
echo "  ╠══════════════════════════════════════════╣"
printf "  ║  Versão: %-33s║\n" "$NEW_VERSION"
printf "  ║  Tempo : %-33s║\n" "${TOTAL}s"
printf "  ║  Log   : %-33s║\n" "$LOG"
echo "  ╚══════════════════════════════════════════╝"
echo ""
