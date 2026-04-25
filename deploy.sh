#!/usr/bin/env bash
set -euo pipefail

# ── Config ────────────────────────────────────────────────────────────────────
# Set these to match your environment before running.
NAS_HOST="your-nas-hostname"
NAS_USER="your-nas-username"
NC_CONTAINER="nextcloud-aio-nextcloud"
NC_CUSTOM_APPS="/var/www/html/custom_apps"
APP_ID="deductiblelog"
STAGE_DIR="/tmp/dl-deploy"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# ── Build ──────────────────────────────────────────────────────────────────────
echo "==> Building frontend…"
cd "$SCRIPT_DIR"
npm run build

# ── Transfer via tar | ssh ────────────────────────────────────────────────────
# UGOS Pro intercepts rsync connections via its own daemon; use tar pipe instead.
echo "==> Transferring to ${NAS_HOST}:${STAGE_DIR}/ …"
ssh "${NAS_USER}@${NAS_HOST}" "rm -rf '${STAGE_DIR}' && mkdir -p '${STAGE_DIR}'"
tar -czf - \
  --exclude='.git' \
  --exclude='node_modules' \
  --exclude='.gitignore' \
  --exclude='*.md' \
  --exclude='deploy.sh' \
  --exclude='package*.json' \
  --exclude='vite.config.js' \
  --exclude='composer.json' \
  --exclude='src' \
  -C "${SCRIPT_DIR}" \
  . \
  | ssh "${NAS_USER}@${NAS_HOST}" "tar -xzf - -C '${STAGE_DIR}'"

# ── docker cp into container ──────────────────────────────────────────────────
# Requires NAS_USER to be in the docker group (no sudo needed).
echo "==> Copying into container ${NC_CONTAINER}…"
ssh "${NAS_USER}@${NAS_HOST}" \
  "docker cp '${STAGE_DIR}/.' '${NC_CONTAINER}:${NC_CUSTOM_APPS}/${APP_ID}/'"

# ── Enable app (idempotent; runs migrations on first install) ─────────────────
echo "==> Enabling app (runs DB migrations if needed)…"
ssh "${NAS_USER}@${NAS_HOST}" \
  "docker exec -u www-data '${NC_CONTAINER}' php occ app:enable ${APP_ID}"

# ── Cleanup ───────────────────────────────────────────────────────────────────
echo "==> Cleaning up…"
ssh "${NAS_USER}@${NAS_HOST}" "rm -rf '${STAGE_DIR}'"

echo "==> Done. App deployed to ${NAS_HOST}."
