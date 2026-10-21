#!/usr/bin/env bash
# Ship a new version. Run on the server as root:
#   stockpoint-deploy            (wrapper installed at /usr/local/bin)
#   sudo bash /var/www/stockpoint/deploy/deploy.sh
#
# Order matters and is deliberate:
#   1. back up, so a bad migration is never the end of the story
#   2. fetch the code and its PHP dependencies (artisan cannot run without them)
#   3. build the SPA while the shop is still up (it only writes static files)
#   4. show what the database is about to do, then migrate and seed
#   5. re-cache, restart the workers
# The shop is in maintenance mode only from the migration to the last cache,
# so it is down for seconds rather than for the whole build.
#
# A failure stops the script (set -e). If it stopped after `down`, fix the
# cause and rerun; `php artisan up` brings the previous version back.
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/stockpoint}"
BRANCH="${BRANCH:-main}"
# The Deployments screen writes the version it was asked for here. It is
# validated again below, so a bad value can only ever fall back to the branch.
REF_FILE="${REF_FILE:-$APP_DIR/storage/app/private/deployments/requested-ref}"
PHP_FPM="${PHP_FPM:-php8.5-fpm}"
export COMPOSER_ALLOW_SUPERUSER=1

cd "$APP_DIR"
artisan() { sudo -u www-data php artisan "$@"; }
# Each heading shows the seconds since the deploy began, so a slow step is obvious.
step() { echo "==> $1  [${SECONDS}s]"; }

step "Backup before deploying"
APP_DIR="$APP_DIR" bash deploy/backup.sh

step "Code"
BEFORE="$(git rev-parse --short HEAD)"
git fetch --prune --tags --force origin

TARGET="origin/${BRANCH}"
if [ -f "$REF_FILE" ]; then
  REQUESTED="$(tr -d "[:space:]" < "$REF_FILE")"
  : > "$REF_FILE"   # one deploy, one request
  if [ -n "$REQUESTED" ]; then
    if printf '%s' "$REQUESTED" | grep -Eq '^v[0-9]+\.[0-9]+\.[0-9]+$' && git rev-parse -q --verify "refs/tags/${REQUESTED}" >/dev/null; then
      TARGET="refs/tags/${REQUESTED}"
      echo "    deploying version ${REQUESTED}"
    else
      echo "    ignoring '${REQUESTED}': not a released version" >&2
    fi
  fi
fi

git reset --hard "$TARGET"
git log --oneline "${BEFORE}..HEAD" | head -20 || true
echo "    now at $(git describe --tags --always)"
composer install --no-dev --optimize-autoloader --no-interaction

# Before `down`: npm ci and the build take minutes, and none of it needs the
# database, so the shop keeps trading while it runs. Each is skipped when
# nothing it depends on changed: packages are reinstalled only when the
# lockfile changes, and the SPA is rebuilt only when frontend/ changed since
# the commit it was last built from (recorded in storage/app/private/spa-built-from).
step "Frontend"
LOCK_HASH="$(sha1sum frontend/package-lock.json | cut -d' ' -f1)"
if [ "$(cat frontend/node_modules/.deploy-lock-hash 2>/dev/null)" != "$LOCK_HASH" ]; then
  (cd frontend && npm ci --no-audit --no-fund)
  echo "$LOCK_HASH" > frontend/node_modules/.deploy-lock-hash
else
  echo "    packages unchanged — npm ci skipped"
fi
BUILT_FROM="$(cat storage/app/private/spa-built-from 2>/dev/null || true)"
if [ -n "$BUILT_FROM" ] && [ -f public/spa/index.html ] && git cat-file -e "${BUILT_FROM}^{commit}" 2>/dev/null \
  && git diff --quiet "$BUILT_FROM" HEAD -- frontend; then
  echo "    frontend unchanged since ${BUILT_FROM:0:7} — build skipped"
else
  (cd frontend && npm run build)
  git rev-parse HEAD > storage/app/private/spa-built-from
fi

step "Database"
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

# Only files not already www-data's, and never node_modules (tens of
# thousands of files nothing at runtime writes to).
find "$APP_DIR" -path "$APP_DIR/frontend/node_modules" -prune -o ! -user www-data -exec chown www-data:www-data {} +
chmod -R ug+rwX storage bootstrap/cache

step "Caches and workers"
artisan config:cache
artisan route:cache
artisan view:cache
artisan event:cache
artisan queue:restart
systemctl reload "$PHP_FPM"   # drops the old opcache
artisan up

step "Deployed $(git describe --tags --always) — $(git log -1 --pretty=%s)"
