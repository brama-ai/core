#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"

cd "${REPO_ROOT}"

echo "==> Installing OpenCode plugins..."
if [ -f .opencode/package.json ]; then
  (
    cd .opencode
    bun install
  )
fi

echo "==> Installing oh-my-opencode (multi-model orchestration)..."
if command -v opencode >/dev/null 2>&1; then
  bunx oh-my-opencode install --no-tui --claude=max20 --gemini=no --copilot=no
  echo "  oh-my-opencode installed. Verify: opencode --version"
else
  echo "  OpenCode not found, skipping oh-my-opencode install"
fi
