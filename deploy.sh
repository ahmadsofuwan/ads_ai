#!/usr/bin/env bash
set -euo pipefail

# ==============================================================================
# Auto-Deploy Script — Omni-Channel Ads AI Publisher
# Triggered via GitHub Actions SSH (.github/workflows/deploy.yml) or CLI.
# Strictly uses SQLite (DB_CONNECTION=sqlite) per environment rules.
# ==============================================================================

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BRANCH="${1:-main}"

echo "============================================================"
echo "🚀 Starting Auto-Deploy from Git (Branch: ${BRANCH})"
echo "📂 Directory: ${APP_DIR}"
echo "============================================================"

cd "${APP_DIR}"

echo "➡️ [1/4] Fetching & pulling latest commits from origin/${BRANCH}..."
git fetch origin "${BRANCH}"
git pull --ff-only origin "${BRANCH}"

echo "➡️ [2/4] Installing Composer dependencies..."
if command -v composer >/dev/null 2>&1; then
    composer install --no-interaction --prefer-dist --optimize-autoloader
fi

echo "➡️ [3/4] Running SQLite database migrations..."
DB_CONNECTION=sqlite php artisan migrate --force

echo "➡️ [4/4] Clearing caches & restarting queue workers..."
php artisan optimize:clear
php artisan queue:restart

COMMIT_INFO="$(git log -1 --oneline)"
DB_CONNECTION=sqlite php artisan tinker --execute="\App\Models\Setting::current()->update(['last_deployed_at' => now(), 'last_deploy_commit' => addslashes('${COMMIT_INFO}'), 'last_deploy_status' => 'SUCCESS', 'last_deploy_log' => 'Deployed via GitHub Actions SSH (.github/workflows/deploy.yml) — ' . addslashes('${COMMIT_INFO}')]);" >/dev/null 2>&1 || true

echo "============================================================"
echo "✅ Auto-Deploy Completed Successfully!"
echo "📌 Active Commit: ${COMMIT_INFO}"
echo "============================================================"
