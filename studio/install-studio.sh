#!/bin/bash
# Instala o Studio Fenor em /var/www/studio
# Chamado pelo install.sh — variável REPO_RAW disponível no ambiente

set -e

REPO="https://github.com/FENOR-IA/fenor.ia.git"
TMPDIR=$(mktemp -d)

git clone --depth=1 "$REPO" "$TMPDIR/fenor" 2>/dev/null

mkdir -p /var/www/studio
cp -r "$TMPDIR/fenor/studio/." /var/www/studio/

rm -rf "$TMPDIR"

chown -R fenor:www-data /var/www/studio/
chmod -R 755 /var/www/studio/
find /var/www/studio -name "*.php" -exec chmod 644 {} \;
