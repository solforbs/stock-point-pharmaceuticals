#!/usr/bin/env bash
# Ship a new version to a server set up by provision.sh. Run as root:
#   sudo bash /var/www/pharmacy_erp/deploy/deploy.sh
# Code is fetched and built first; maintenance mode covers only migrate and
# re-cache, so the shop is down for seconds. A failure stops the script with
# set -e; if it stopped after `down`, fix the cause and rerun, or run
# `php artisan up` to bring the previous version back.
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/pharmacy_erp}"
BRANCH="${BRANCH:-main}"
export COMPOSER_ALLOW_SUPERUSER=1
cd "$APP_DIR"
artisan() { sudo -u www-data php artisan "$@"; }

echo "==> Backup before deploying"
bash deploy/backup.sh

echo "==> Code"
git fetch --prune origin
git reset --hard "origin/${BRANCH}"
composer install --no-dev --optimize-autoloader --no-interaction
(cd frontend && npm ci && npm run build)
chown -R www-data:www-data "$APP_DIR"
chmod -R ug+rwX storage bootstrap/cache

echo "==> Migrate"
artisan down --retry=15
artisan migrate --force
artisan db:seed --class=PermissionSeeder --force
artisan db:seed --class=RoleSeeder --force
artisan finance:open-periods
artisan config:cache
artisan route:cache
artisan view:cache
artisan event:cache
artisan queue:restart
artisan up

echo "==> Deployed $(git rev-parse --short HEAD)"
