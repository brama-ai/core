#!/usr/bin/env bash
# Sync shared skills from skills/ into agent-specific directories.
#
# Source of truth: skills/ (committed to repo)
# Targets:
#   - .claude/skills/     (Claude Code)
#   - .cursor/skills/     (Cursor / Antigravity — future)
#   - .codex/skills/      (Codex — future)
#
# Usage:
#   ./scripts/sync-skills.sh          # sync all targets
#   ./scripts/sync-skills.sh claude   # sync only Claude
#   ./scripts/sync-skills.sh cursor   # sync only Cursor
#   ./scripts/sync-skills.sh codex    # sync only Codex

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SOURCE="$REPO_ROOT/skills"

if [ ! -d "$SOURCE" ]; then
  echo "Error: skills/ directory not found at $SOURCE"
  exit 1
fi

sync_target() {
  local name="$1"
  local target="$2"

  mkdir -p "$target"
  rsync -a --delete "$SOURCE/" "$target/"
  echo "Synced skills/ -> $target ($name)"
}

targets="${1:-all}"

do_claude() {
  sync_target "Claude Code" "$REPO_ROOT/.claude/skills"
}

do_cursor() {
  sync_target "Cursor" "$REPO_ROOT/.cursor/skills"
}

do_codex() {
  # Uncomment when Codex is set up:
  # sync_target "Codex" "$REPO_ROOT/.codex/skills"
  echo "Codex sync not yet configured. Uncomment in script when ready."
}

case "$targets" in
  claude) do_claude ;;
  cursor) do_cursor ;;
  codex)  do_codex ;;
  all)    do_claude; do_cursor; do_codex ;;
  *)
    echo "Usage: $0 [claude|cursor|codex|all]"
    exit 1
    ;;
esac
