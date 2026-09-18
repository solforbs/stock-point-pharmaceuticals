#!/usr/bin/env bash
# Nightly (and pre-deploy) backup: a consistent MySQL dump plus uploaded
# files, kept for 30 days. Copy /var/backups/pharmacy_erp off the server
# regularly — a backup on the same disk does not survive a lost VPS.
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/pharmacy_erp}"
DEST="${BACKUP_DIR:-/var/backups/pharmacy_erp}"
KEEP_DAYS="${KEEP_DAYS:-30}"
STAMP="$(date +%Y%m%d-%H%M%S)"

env_value() { grep -E "^$1=" "$APP_DIR/.env" | tail -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'; }
DB_NAME="$(env_value DB_DATABASE)"
DB_USER="$(env_value DB_USERNAME)"
DB_PASS="$(env_value DB_PASSWORD)"

mkdir -p "$DEST"
chmod 700 "$DEST"

MYSQL_PWD="$DB_PASS" mysqldump --user="$DB_USER" --single-transaction --quick --routines --triggers \
  --no-tablespaces "$DB_NAME" | gzip -9 > "$DEST/db-$STAMP.sql.gz"
tar -czf "$DEST/files-$STAMP.tar.gz" -C "$APP_DIR" storage/app .env

find "$DEST" -type f -mtime +"$KEEP_DAYS" -delete
echo "Backup written to $DEST/db-$STAMP.sql.gz"
