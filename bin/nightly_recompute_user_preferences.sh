#!/usr/bin/env bash
set -euo pipefail

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PHP_BIN="${PHP_BIN:-php}"
APP_ENV_VALUE="${APP_ENV_VALUE:-prod}"
BATCH_SIZE="${BATCH_SIZE:-500}"
LOCK_FILE="${LOCK_FILE:-$PROJECT_DIR/var/lock/recompute_user_preferences.lock}"

mkdir -p "$(dirname "$LOCK_FILE")"

exec 9>"$LOCK_FILE"
if ! flock -n 9; then
  echo "[recompute-user-preferences] another run is active; exiting."
  exit 0
fi

cd "$PROJECT_DIR"

APP_ENV="$APP_ENV_VALUE" APP_DEBUG=0 "$PHP_BIN" bin/console app:recompute-user-preferences \
  --all \
  --batch-size="$BATCH_SIZE" \
  --no-interaction
