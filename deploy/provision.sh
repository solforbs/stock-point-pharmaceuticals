#!/usr/bin/env bash
# One-time setup of a fresh Ubuntu 22.04 / 24.04 VPS for the Stockpoint ERP.
# Run as root:
#   DOMAIN=erp.example.co.ke ADMIN_EMAIL=admin@example.co.ke ORG_KRA_PIN=P051234567X \
#   REPO=git@github.com:solforbs/stock-point-pharmaceuticals.git bash deploy/provision.sh
#
# It installs nginx, MySQL, PHP 8.3-FPM, Node 20 and Composer; creates the
# database; clones the app to /var/www/stockpoint; writes .env; migrates
# and seeds (catalogue, VAT codes, supplier, roles); builds the SPA; and wires
# nginx, the queue worker, the scheduler, daily backups and HTTPS.
set -euo pipefail

: "${DOMAIN:?set DOMAIN, e.g. erp.example.co.ke}"
: "${ADMIN_EMAIL:?set ADMIN_EMAIL for the first administrator and the TLS certificate}"
: "${ORG_KRA_PIN:?set ORG_KRA_PIN (the business KRA PIN printed on invoices)}"
: "${REPO:?set REPO (git URL of this repository)}"
BRANCH="${BRANCH:-main}"
APP_DIR="${APP_DIR:-/var/www/stockpoint}"
DB_NAME="${DB_NAME:-stockpoint}"
DB_USER="${DB_USER:-stockpoint}"
DB_PASSWORD="${DB_PASSWORD:-$(openssl rand -base64 24 | tr -d '/+=')}"
SKIP_TLS="${SKIP_TLS:-0}"

export DEBIAN_FRONTEND=noninteractive
export COMPOSER_ALLOW_SUPERUSER=1

echo "==> Packages"
apt-get update -y
apt-get install -y software-properties-common curl git unzip ufw cron
if ! apt-cache policy php8.5-fpm | grep -q Candidate: || apt-cache policy php8.5-fpm | grep -q "Candidate: (none)"; then
  add-apt-repository -y ppa:ondrej/php
  apt-get update -y
fi
apt-get install -y nginx mysql-server supervisor \
  php8.5-fpm php8.5-cli php8.5-mysql php8.5-mbstring php8.5-xml php8.5-bcmath \
  php8.5-curl php8.5-zip php8.5-intl php8.5-gd php8.5-sodium
if ! command -v node >/dev/null || [ "$(node -v | cut -d. -f1 | tr -d v)" -lt 20 ]; then
  curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
  apt-get install -y nodejs
fi
if ! command -v composer >/dev/null; then
  curl -fsSL https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi

echo "==> Firewall"
ufw allow OpenSSH
ufw allow 'Nginx Full'
ufw --force enable

echo "==> Database"
mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL

echo "==> Code"
if [ ! -d "$APP_DIR/.git" ]; then
  git clone --branch "$BRANCH" "$REPO" "$APP_DIR"
fi
cd "$APP_DIR"

if [ ! -f .env ]; then
  cp .env.production.example .env
  sed -i "s|CHANGE_ME_DOMAIN|${DOMAIN}|g; s|CHANGE_ME_DB_PASSWORD|${DB_PASSWORD}|; s|CHANGE_ME_KRA_PIN|${ORG_KRA_PIN}|; s|^DB_DATABASE=.*|DB_DATABASE=${DB_NAME}|; s|^DB_USERNAME=.*|DB_USERNAME=${DB_USER}|" .env
  [ -n "${ORG_NAME:-}" ] && sed -i "s|^ORG_NAME=.*|ORG_NAME=\"${ORG_NAME}\"|" .env
  [ -n "${ORG_BRANCH_CODE:-}" ] && sed -i "s|^ORG_BRANCH_CODE=.*|ORG_BRANCH_CODE=${ORG_BRANCH_CODE}|" .env
  [ -n "${ORG_BRANCH_NAME:-}" ] && sed -i "s|^ORG_BRANCH_NAME=.*|ORG_BRANCH_NAME=\"${ORG_BRANCH_NAME}\"|" .env
  [ -n "${ORG_COUNTY:-}" ] && sed -i "s|^ORG_COUNTY=.*|ORG_COUNTY=${ORG_COUNTY}|" .env
  # Without HTTPS the browser would drop a secure-only session cookie and nobody could sign in.
  if [ "$SKIP_TLS" = "1" ]; then
    sed -i "s|^SESSION_SECURE_COOKIE=.*|SESSION_SECURE_COOKIE=false|; s|^APP_URL=https://|APP_URL=http://|" .env
  fi
fi

composer install --no-dev --optimize-autoloader --no-interaction
grep -q '^APP_KEY=base64' .env || php artisan key:generate --force

(cd frontend && npm ci && npm run build)

php artisan migrate --force
php artisan db:seed --force
php artisan finance:open-periods

chown -R www-data:www-data "$APP_DIR"
chmod -R ug+rwX storage bootstrap/cache

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

echo "==> nginx"
sed "s|__DOMAIN__|${DOMAIN}|g; s|__APP_DIR__|${APP_DIR}|g" deploy/nginx.conf > /etc/nginx/sites-available/stockpoint
ln -sf /etc/nginx/sites-available/stockpoint /etc/nginx/sites-enabled/stockpoint
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx

echo "==> Queue worker (eTIMS submissions) and scheduler"
sed "s|__APP_DIR__|${APP_DIR}|g" deploy/supervisor-queue.conf > /etc/supervisor/conf.d/stockpoint-queue.conf
supervisorctl reread && supervisorctl update && supervisorctl start stockpoint-queue:* || true
sed "s|__APP_DIR__|${APP_DIR}|g" deploy/cron > /etc/cron.d/stockpoint
chmod 644 /etc/cron.d/stockpoint

echo "==> Backups"
install -d -o root -g root -m 700 /var/backups/stockpoint
chmod +x deploy/backup.sh deploy/deploy.sh

echo "==> HTTPS"
if [ "$SKIP_TLS" != "1" ]; then
  apt-get install -y certbot python3-certbot-nginx
  certbot --nginx -d "$DOMAIN" --non-interactive --agree-tos -m "$ADMIN_EMAIL" --redirect
fi

echo "==> First administrator"
sudo -u www-data php artisan user:create-admin "$ADMIN_EMAIL" --name="System Administrator" --full-access --no-interaction

cat <<DONE

Provisioned https://${DOMAIN}
  Database user ${DB_USER} password: ${DB_PASSWORD}   (also in ${APP_DIR}/.env)
  The administrator password is printed above; sign in and change it.
  Updates: sudo bash ${APP_DIR}/deploy/deploy.sh
DONE
