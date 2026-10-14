#!/usr/bin/env bash
# =====================================================================
# MINIMERCADO BJA - Backup do banco de dados
#
# Uso manual:   sudo bash backup_banco.sh
# Automatico (a cada 15 min durante o evento):
#   sudo crontab -e   e adicione a linha abaixo:
#   */15 * * * * bash /var/www/html/minimercadobja/backup_banco.sh >/dev/null 2>&1
#
# Os backups ficam em /var/backups/minimercado (mantem os 200 mais recentes).
# DICA: no fim de cada dia, copie essa pasta para um pendrive.
# =====================================================================
set -euo pipefail

DIR_APP="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CONFIG="$DIR_APP/config/database.local.php"
PASTA="/var/backups/minimercado"
if [ "$(id -u)" -ne 0 ]; then echo "Rode com sudo: sudo bash backup_banco.sh"; exit 1; fi
mkdir -p "$PASTA"

ler() { php -r '$c = require $argv[1]; echo $c[$argv[2]] ?? "";' "$CONFIG" "$1"; }

BANCO="$(ler banco)"
USUARIO="$(ler usuario)"
SENHA="$(ler senha)"

ARQUIVO="$PASTA/minimercado_$(date +%Y%m%d_%H%M%S).sql"
MYSQL_PWD="$SENHA" mysqldump -u "$USUARIO" --single-transaction --routines "$BANCO" > "$ARQUIVO"
gzip -f "$ARQUIVO"

# Mantem apenas os 200 backups mais recentes
ls -1t "$PASTA"/minimercado_*.sql.gz 2>/dev/null | tail -n +201 | xargs -r rm -f
echo "Backup criado: $ARQUIVO.gz"
