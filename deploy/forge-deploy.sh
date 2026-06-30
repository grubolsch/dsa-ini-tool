#!/usr/bin/env bash
#
# Laravel Forge deploy script for the DSA INI tool (single-domain setup).
# Paste this into Forge → Site → "App" → Deploy Script. Replace DOMAIN below.
#
# Forge provides these variables at deploy time:
#   $FORGE_SITE_PATH   e.g. /home/forge/your-domain.com
#   $FORGE_SITE_BRANCH e.g. main
#   $FORGE_PHP         e.g. php8.4
#   $FORGE_COMPOSER    composer binary
#   $FORGE_PHP_FPM     e.g. php8.4-fpm
#
set -e

DOMAIN="your-domain.com"   # <-- change me (used for the built-in Vite/Mercure URLs)

cd "$FORGE_SITE_PATH"
git pull origin "$FORGE_SITE_BRANCH"

# ---------- Backend (Symfony) ----------
cd backend
$FORGE_COMPOSER install --no-interaction --prefer-dist --optimize-autoloader --no-dev
$FORGE_PHP bin/console doctrine:migrations:migrate --no-interaction
$FORGE_PHP bin/console cache:clear
$FORGE_PHP bin/console cache:warmup

# ---------- Frontend (React / Vite) ----------
# Vite bakes these in at BUILD time, so they must be exported before `npm run build`.
cd ../frontend
export VITE_MERCURE_URL="https://${DOMAIN}/.well-known/mercure"
export VITE_PLAYER_BASE_URL="https://${DOMAIN}"
npm ci
npm run build

# ---------- Reload PHP-FPM ----------
( flock -w 10 9 || exit 1; echo 'Reloading FPM…'; sudo -S service "$FORGE_PHP_FPM" reload ) 9>/tmp/fpmlock
