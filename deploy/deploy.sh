#!/usr/bin/env bash
# Ship a new version. Run on the server as root:
#   stockpoint-deploy            (wrapper installed at /usr/local/bin)
#   sudo bash /var/www/stockpoint/deploy/deploy.sh
#
# Order matters and is deliberate:
#   1. back up, so a bad migration is never the end of the story
#   2. fetch the code and its PHP dependencies (artisan cannot run without them)
#   3. show what the database is about to do, then migrate and seed
#   4. build the SPA, re-cache, restart the workers
# The shop is in maintenance mode only from the migration to the last cache,
# so it is down for seconds rather than for the whole build.
#
# A failure stops the script (set -e). If it stopped after `down`, fix the
# cause and rerun; `php artisan up` brings the previous version back.
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/stockpoint}"
BRANCH="${BRANCH:-main}"
PHP_FPM="${PHP_FPM:-php8.5-fpm}"
export COMPOSER_ALLOW_SUPERUSER=1

cd "$APP_DIR"
artisan() { sudo -u www-data php artisan "$@"; }

echo "==> Backup before deploying"
APP_DIR="$APP_DIR" bash deploy/backup.sh

echo "==> Code"
BEFORE="$(git rev-parse --short HEAD)"
git fetch --prune origin
git reset --hard "origin/${BRANCH}"
git log --oneline "${BEFORE}..HEAD" | head -20 || true
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> Database"
# What is about to change, named before it happens — a deploy that silently
# migrates is a deploy nobody can review afterwards.
PENDING="$(artisan migrate:status 2>/dev/null | grep -c 'Pending' || true)"
echo "    ${PENDING} pending migration(s)"
artisan migrate:status 2>/dev/null | grep 'Pending' || true

artisan down --retry=15
artisan migrate --force

# Reference data only: these seeders are idempotent (firstOrCreate) and never
# touch the books. Demo seeders are refused outside local by DatabaseSeeder.
for seeder in PermissionSeeder RoleSeeder TaxCodeSeeder UnitOfMeasureSeeder DosageFormSeeder StorageConditionSeeder; do
  artisan db:seed --class="$seeder" --force --no-interaction
done
artisan finance:open-periods

echo "==> Frontend"
(cd frontend && npm ci --no-audit --no-fund && npm run build)

chown -R www-data:www-data "$APP_DIR"
chmod -R ug+rwX storage bootstrap/cache

echo "==> Caches and workers"
artisan config:cache
artisan route:cache
artisan view:cache
artisan event:cache
artisan queue:restart
systemctl reload "$PHP_FPM"   # drops the old opcache
artisan up

echo "==> Deployed $(git rev-parse --short HEAD) ($(git log -1 --pretty=%s))"
