#!/usr/bin/env bash
set -euo pipefail

# ── Config ────────────────────────────────────────────────────────────────────
NAS_HOST="spectre-nas"
NAS_USER="eckardmo"
NC_CUSTOM_APPS="/volume2/@docker/volumes/nextcloud_aio_nextcloud/_data/custom_apps"
APP_ID="deductiblelog"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# ── Build ──────────────────────────────────────────────────────────────────────
echo "==> Building frontend…"
cd "$SCRIPT_DIR"
npm run build

# ── Sync ──────────────────────────────────────────────────────────────────────
echo "==> Rsyncing to ${NAS_HOST}:${NC_CUSTOM_APPS}/${APP_ID}/"
rsync -az --delete \
  --exclude='.git' \
  --exclude='node_modules' \
  --exclude='.gitignore' \
  --exclude='*.md' \
  --exclude='deploy.sh' \
  --exclude='package*.json' \
  --exclude='vite.config.js' \
  --exclude='composer.json' \
  --exclude='src/' \
  --rsh="ssh -l ${NAS_USER}" \
  "$SCRIPT_DIR/" \
  "${NAS_HOST}:${NC_CUSTOM_APPS}/${APP_ID}/"

# ── occ upgrade ───────────────────────────────────────────────────────────────
echo "==> Running occ upgrade on ${NAS_HOST}…"
ssh "${NAS_USER}@${NAS_HOST}" \
  "sudo docker exec -u www-data nextcloud-aio-nextcloud php occ upgrade --no-interaction"

echo "==> Done. App deployed to ${NAS_HOST}."
