#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"

cd "${REPO_ROOT}"

echo "==> Installing Node dependencies..."
for js_dir in . apps/knowledge-agent apps/wiki-agent; do
  if [ -f "${js_dir}/package.json" ]; then
    echo "  npm install (${js_dir})"
    (
      cd "${js_dir}"
      npm install
    )
  fi
done
