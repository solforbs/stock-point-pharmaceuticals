#!/usr/bin/env bash
# Nightly (and pre-deploy) backup: a consistent MySQL dump plus uploaded
# files, kept for 30 days. Copy /var/backups/stockpoint off the server
# regularly — a backup on the same disk does not survive a lost VPS.
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/stockpoint}"
DEST="${BACKUP_DIR:-/var/backups/stockpoint}"
KEEP_DAYS="${KEEP_DAYS:-30}"
STAMP="$(date +%Y%m%d-%H%M%S)"

env_value() { grep -E "^$1=" "$APP_DIR/.env" | tail -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'; }
DB_NAME="$(env_value DB_DATABASE)"
DB_USER="$(env_value DB_USERNAME)"
DB_PASS="$(env_value DB_PASSWORD)"

mkdir -p "$DEST"
chmod 700 "$DEST"

# gzip -6: nearly the size of -9 at a fraction of the time on a large dump.
MYSQL_PWD="$DB_PASS" mysqldump --user="$DB_USER" --single-transaction --quick --routines --triggers \
  --no-tablespaces "$DB_NAME" | gzip -6 > "$DEST/db-$STAMP.sql.gz"
# The in-app backup folder holds earlier database dumps; archiving it again
# made every backup bigger and slower than the last.
tar -czf "$DEST/files-$STAMP.tar.gz" -C "$APP_DIR" \
  --exclude=storage/app/private/backups --exclude=storage/app/backups \
  storage/app .env

find "$DEST" -type f -mtime +"$KEEP_DAYS" -delete
echo "Backup written to $DEST/db-$STAMP.sql.gz"
