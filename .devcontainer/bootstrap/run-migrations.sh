#!/usr/bin/env bash
set -euo pipefail

export PGPASSWORD="${PGPASSWORD:-app}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"

cd "${REPO_ROOT}"

echo "==> Running database migrations..."
if ! pg_isready -h postgres -U app -q 2>/dev/null; then
  echo "  PostgreSQL is not reachable, skipping migrations"
  exit 0
fi

for console in apps/core/bin/console apps/knowledge-agent/bin/console; do
  if [ -f "${console}" ]; then
    echo "  doctrine:migrations:migrate (${console})"
    php "${console}" doctrine:migrations:migrate --no-interaction
    APP_ENV=test php "${console}" doctrine:migrations:migrate --no-interaction
  fi
done
