#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"

cd "${REPO_ROOT}"

echo "==> Installing PHP dependencies..."
for app_dir in apps/core apps/knowledge-agent apps/dev-agent apps/dev-reporter-agent apps/hello-agent; do
  if [ -f "${app_dir}/composer.json" ]; then
    echo "  composer install (${app_dir})"
    (
      cd "${app_dir}"
      composer install --no-interaction --prefer-dist
    )
  fi
done
