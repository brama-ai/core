#!/usr/bin/env bash
# Run a single pipeline task in an isolated worktree.
# Usage: pipeline-run-task.sh <task-file.md>
#
# Creates a temporary worktree, runs pipeline.sh inside it, cleans up after.
# Designed to be called from the monitor to run tasks in parallel with the batch.

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
TASK_FILE="$1"

if [[ ! -f "$TASK_FILE" ]]; then
  echo "Error: task file not found: $TASK_FILE"
  exit 1
fi

SLUG=$(basename "$TASK_FILE" .md)
BRANCH="pipeline/${SLUG}"
WORKTREE_BASE="$REPO_ROOT/.pipeline-worktrees"
WORKER_ID="adhoc-$$"
WT="$WORKTREE_BASE/$WORKER_ID"

cleanup() {
  git -C "$REPO_ROOT" worktree remove --force "$WT" 2>/dev/null || rm -rf "$WT"
  git -C "$REPO_ROOT" worktree prune 2>/dev/null || true
}
trap cleanup EXIT

# Create worktree
mkdir -p "$WORKTREE_BASE"
git -C "$REPO_ROOT" worktree prune 2>/dev/null || true
git -C "$REPO_ROOT" worktree add --detach "$WT" HEAD

# Symlink dependencies (vendor, node_modules, var, .venv)
while IFS= read -r dep_dir; do
  rel_path="${dep_dir#"$REPO_ROOT"/}"
  mkdir -p "$WT/$(dirname "$rel_path")"
  [[ ! -e "$WT/$rel_path" ]] && ln -s "$dep_dir" "$WT/$rel_path" 2>/dev/null || true
done < <(find "$REPO_ROOT" -maxdepth 3 -type d \( -name vendor -o -name node_modules -o -name var -o -name '.venv' \) 2>/dev/null)

# Symlink .local and tasks/artifacts
[[ -d "$REPO_ROOT/.local" && ! -L "$WT/.local" ]] && ln -s "$REPO_ROOT/.local" "$WT/.local" || true
mkdir -p "$REPO_ROOT/tasks/artifacts"
[[ ! -L "$WT/tasks/artifacts" ]] && ln -s "$REPO_ROOT/tasks/artifacts" "$WT/tasks/artifacts" 2>/dev/null || true

# Clear opencode sandbox restrictions
if command -v sqlite3 &>/dev/null; then
  local_oc_db="$HOME/.local/share/opencode/db/opencode.db"
  if [[ -f "$local_oc_db" ]]; then
    sqlite3 "$local_oc_db" "UPDATE project SET sandboxes = '[]' WHERE worktree = '$(printf '%s' "$REPO_ROOT" | sed "s/'/''/g")'" 2>/dev/null || true
  fi
fi

# Copy task file into worktree
WT_TASK="$WT/.pipeline-task.md"
cp "$TASK_FILE" "$WT_TASK"

# Run pipeline
echo "=== Running task: $SLUG (worktree: $WORKER_ID) ==="
"$WT/scripts/pipeline.sh" --branch "$BRANCH" --task-file "$WT_TASK" || exit $?
