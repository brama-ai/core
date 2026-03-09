#!/bin/sh
set -eu

if [ "${MIGRATE_ON_START:-1}" = "1" ]; then
  echo "[dev-agent] Running startup migrations (best effort)..."
  if ! php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration; then
    echo "[dev-agent] Startup migrations failed; continuing container startup."
  fi
fi

# Start pipeline worker in background
echo "[dev-agent] Starting pipeline worker..."
php bin/console dev:pipeline:worker &

exec "$@"
