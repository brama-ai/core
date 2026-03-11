#!/usr/bin/env bash
#
# Batch runner for the multi-agent pipeline.
# Reads tasks from a text file or a tasks/ folder and runs the pipeline for each.
#
# Usage:
#   ./scripts/pipeline-batch.sh tasks.txt                     # one-line-per-task file
#   ./scripts/pipeline-batch.sh tasks/                        # folder-based kanban
#   ./scripts/pipeline-batch.sh --workers 2 tasks/            # 2 parallel workers
#   ./scripts/pipeline-batch.sh --no-stop-on-failure tasks.txt
#
# Folder mode: reads .md files from tasks/todo/, moves them through
# in-progress/ → done/ (or failed/) as they complete.
#
# Text file format (one task per line, empty lines and # comments ignored):
#   Add streaming support to A2A gateway
#   Implement agent marketplace search
#   # This is a comment
#
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SCRIPT_DIR="$REPO_ROOT/scripts"
BATCH_TIMESTAMP=$(date +%Y%m%d_%H%M%S)
RESULTS_FILE="$REPO_ROOT/.opencode/pipeline/reports/batch_${BATCH_TIMESTAMP}.md"

# ── Single-instance guard ─────────────────────────────────────────────
LOCKFILE="$REPO_ROOT/.opencode/pipeline/.batch.lock"

acquire_lock() {
  mkdir -p "$(dirname "$LOCKFILE")"
  if [[ -f "$LOCKFILE" ]]; then
    local old_pid
    old_pid=$(cat "$LOCKFILE" 2>/dev/null || true)
    if [[ -n "$old_pid" ]] && kill -0 "$old_pid" 2>/dev/null; then
      echo -e "${RED}Error: Another pipeline-batch is already running (PID $old_pid)${NC}"
      echo -e "${YELLOW}Kill it first: kill $old_pid${NC}"
      exit 1
    fi
    # Stale lockfile — previous process died
    echo -e "${YELLOW}Removing stale lockfile (PID $old_pid no longer running)${NC}"
    rm -f "$LOCKFILE"
  fi
  echo "$$" > "$LOCKFILE"
}

release_lock() {
  rm -f "$LOCKFILE"
}

acquire_lock
trap 'release_lock' EXIT

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

# Parse arguments
TASK_SOURCE=""
EXTRA_ARGS=""
STOP_ON_FAILURE=true
WEBHOOK_URL=""
WORKERS=1
WATCH_MODE=false
WATCH_INTERVAL=15  # seconds between polls for new tasks
AUTO_FIX=false
MAX_AUTO_FIX_RETRIES="${PIPELINE_MAX_RETRIES:-10}"

while [[ $# -gt 0 ]]; do
  case "$1" in
    --no-stop-on-failure)
      STOP_ON_FAILURE=false
      shift
      ;;
    --auto-fix)
      AUTO_FIX=true
      shift
      ;;
    --watch)
      WATCH_MODE=true
      shift
      ;;
    --watch-interval)
      WATCH_INTERVAL="$2"
      shift 2
      ;;
    --workers)
      WORKERS="$2"
      if ! [[ "$WORKERS" =~ ^[0-9]+$ ]] || [[ "$WORKERS" -lt 1 ]]; then
        echo -e "${RED}Error: --workers must be a positive integer${NC}"
        exit 1
      fi
      shift 2
      ;;
    --webhook)
      WEBHOOK_URL="$1 $2"
      EXTRA_ARGS="$EXTRA_ARGS $1 $2"
      shift 2
      ;;
    --*)
      EXTRA_ARGS="$EXTRA_ARGS $1"
      if [[ "$1" == "--from" || "$1" == "--only" || "$1" == "--branch" ]]; then
        EXTRA_ARGS="$EXTRA_ARGS $2"
        shift
      fi
      shift
      ;;
    *)
      if [[ -z "$TASK_SOURCE" ]]; then
        TASK_SOURCE="$1"
      fi
      shift
      ;;
  esac
done

# Default to tasks/ folder if it exists and no source was given
if [[ -z "$TASK_SOURCE" && -d "$REPO_ROOT/tasks" ]]; then
  TASK_SOURCE="$REPO_ROOT/tasks"
fi

if [[ -z "$TASK_SOURCE" ]]; then
  echo -e "${RED}Error: No task source provided${NC}"
  echo ""
  echo "Usage: ./scripts/pipeline-batch.sh <tasks-file-or-folder> [options]"
  echo ""
  echo "Two formats supported:"
  echo ""
  echo "  1) Text file (one task per line):"
  echo "     ./scripts/pipeline-batch.sh tasks.txt"
  echo ""
  echo "  2) Folder (one .md file per task in todo/):"
  echo "     ./scripts/pipeline-batch.sh tasks/"
  echo "     Files move: todo/ → in-progress/ → done/ (or failed/)"
  echo ""
  echo "Options:"
  echo "  --workers N           Run N tasks in parallel (default: 1, sequential)"
  echo "  --no-stop-on-failure  Continue to next task even if current fails"
  echo "  --watch               Keep running and pick up new tasks from todo/"
  echo "  --watch-interval N    Seconds between polls for new tasks (default: 15)"
  echo ""
  echo "Pipeline options are passed through to pipeline.sh:"
  echo "  --skip-architect    Skip the architect stage"
  echo "  --from <agent>      Start from a specific agent"
  echo "  --only <agent>      Run only a specific agent"
  echo "  --audit             Add auditor quality gate"
  echo "  --webhook <url>     Send notifications"
  echo "  --telegram          Send Telegram notifications"
  exit 1
fi

# ---------------------------------------------------------------------------
# Task loading — supports both file and folder formats
# ---------------------------------------------------------------------------

# Arrays populated by load_tasks_*:
#   TASK_NAMES[i]  — short name for display, logs, branch slug
#   TASK_FPATHS[i] — file path for --task-file (empty string = use TASK_NAMES as prompt)
TASK_NAMES=()
TASK_FPATHS=()
FOLDER_MODE=false

# Extract first # heading from a markdown file (fallback: filename without extension)
extract_title() {
  local file="$1"
  local title
  title=$(grep -m1 '^# ' "$file" 2>/dev/null | sed 's/^# //')
  if [[ -z "$title" ]]; then
    title=$(basename "$file" .md | sed 's/-/ /g')
  fi
  echo "$title"
}

load_tasks_from_file() {
  local file="$1"
  if [[ ! -f "$file" ]]; then
    echo -e "${RED}Error: Task file not found: ${file}${NC}"
    exit 1
  fi
  while IFS= read -r line; do
    TASK_NAMES+=("$line")
    TASK_FPATHS+=("")
  done < <(grep -v '^\s*$' "$file" | grep -v '^\s*#')
}

load_tasks_from_folder() {
  local folder="$1"
  local todo_dir="$folder/todo"

  if [[ ! -d "$todo_dir" ]]; then
    echo -e "${RED}Error: ${todo_dir}/ not found. Expected folder structure:${NC}"
    echo "  tasks/todo/       — tasks waiting to run"
    echo "  tasks/in-progress/ — currently running"
    echo "  tasks/done/       — completed"
    echo "  tasks/failed/     — failed"
    exit 1
  fi

  # Ensure lifecycle folders exist
  mkdir -p "$folder/in-progress" "$folder/done" "$folder/failed"

  # Read .md files sorted by name for predictable order
  local files=()
  while IFS= read -r -d '' f; do
    files+=("$f")
  done < <(find "$todo_dir" -maxdepth 1 -name '*.md' -print0 | sort -z)

  if [[ ${#files[@]} -eq 0 ]]; then
    echo -e "${YELLOW}No .md files found in ${todo_dir}/${NC}"
    exit 0
  fi

  FOLDER_MODE=true
  for f in "${files[@]}"; do
    local title
    title=$(extract_title "$f")
    TASK_NAMES+=("$title")
    TASK_FPATHS+=("$f")
  done
}

# Determine mode and load tasks
if [[ -f "$TASK_SOURCE" ]]; then
  load_tasks_from_file "$TASK_SOURCE"
elif [[ -d "$TASK_SOURCE" ]]; then
  load_tasks_from_folder "$TASK_SOURCE"
else
  echo -e "${RED}Error: ${TASK_SOURCE} is neither a file nor a directory${NC}"
  exit 1
fi

TOTAL=${#TASK_NAMES[@]}
if [[ $TOTAL -eq 0 ]]; then
  echo -e "${YELLOW}No tasks to run${NC}"
  exit 0
fi

mkdir -p "$(dirname "$RESULTS_FILE")"

PASSED=0
FAILED=0
STOPPED=false

# Save starting branch and ensure we're on it (recover from previous crash)
ORIGINAL_BRANCH=$(git -C "$REPO_ROOT" branch --show-current)
EXPECTED_BRANCH="${PIPELINE_MAIN_BRANCH:-main}"
if [[ "$ORIGINAL_BRANCH" != "$EXPECTED_BRANCH" ]]; then
  echo -e "${YELLOW}Warning: repo is on '${ORIGINAL_BRANCH}' instead of '${EXPECTED_BRANCH}' (likely crashed pipeline)${NC}"
  echo -e "${YELLOW}Switching back to '${EXPECTED_BRANCH}'...${NC}"
  git -C "$REPO_ROOT" stash --include-untracked -m "pipeline-batch: auto-stash before branch recovery" 2>/dev/null || true
  git -C "$REPO_ROOT" checkout "$EXPECTED_BRANCH" 2>/dev/null || true
  ORIGINAL_BRANCH="$EXPECTED_BRANCH"
fi

# Prune stale worktrees from crashed previous runs
git -C "$REPO_ROOT" worktree prune 2>/dev/null || true
if [[ -d "$REPO_ROOT/.pipeline-worktrees" ]]; then
  echo -e "${YELLOW}Cleaning up stale worktrees from previous run...${NC}"
  rm -rf "$REPO_ROOT/.pipeline-worktrees"
  git -C "$REPO_ROOT" worktree prune 2>/dev/null || true
fi

# Display mode
if $FOLDER_MODE; then
  SOURCE_LABEL="${TASK_SOURCE}/todo/ (folder mode)"
else
  SOURCE_LABEL="${TASK_SOURCE} (file mode)"
fi

echo ""
echo -e "${CYAN}╔══════════════════════════════════════════════════╗${NC}"
echo -e "${CYAN}║${NC}     ${YELLOW}Batch Pipeline Runner v2${NC}                    ${CYAN}║${NC}"
echo -e "${CYAN}╚══════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "${BLUE}Source:${NC}           ${SOURCE_LABEL}"
echo -e "${BLUE}Total tasks:${NC}      ${TOTAL}"
echo -e "${BLUE}Workers:${NC}          ${WORKERS}"
echo -e "${BLUE}Stop on failure:${NC}  ${STOP_ON_FAILURE}"
echo -e "${BLUE}Auto-fix retry:${NC}  ${AUTO_FIX} (max ${MAX_AUTO_FIX_RETRIES})"
echo -e "${BLUE}Watch mode:${NC}       ${WATCH_MODE}"
echo -e "${BLUE}Extra args:${NC}       ${EXTRA_ARGS:-none}"
echo -e "${BLUE}Original branch:${NC}  ${ORIGINAL_BRANCH}"
echo -e "${BLUE}Results:${NC}          ${RESULTS_FILE}"
echo ""

# Log header
{
  echo "# Batch Pipeline Results"
  echo ""
  echo "- **Started**: $(date '+%Y-%m-%d %H:%M:%S')"
  echo "- **Source**: ${SOURCE_LABEL}"
  echo "- **Total tasks**: ${TOTAL}"
  echo "- **Workers**: ${WORKERS}"
  echo "- **Stop on failure**: ${STOP_ON_FAILURE}"
  echo ""
  echo "| # | Task | Status | Duration | Branch |"
  echo "|---|------|--------|----------|--------|"
} > "$RESULTS_FILE"

BATCH_START=$(date +%s)

# ---------------------------------------------------------------------------
# Helper: generate a slug from task description
# ---------------------------------------------------------------------------
task_slug() {
  local slug
  slug=$(echo "$1" | tr '[:upper:]' '[:lower:]' | sed 's/[^a-z0-9]/-/g' | sed 's/--*/-/g' | sed 's/^-//' | sed 's/-$//' | cut -c1-50)
  # If slug is empty (non-ASCII title), use task file index as context
  if [[ -z "$slug" ]]; then
    # Try to find matching file and use its basename
    for i in "${!TASK_NAMES[@]}"; do
      if [[ "${TASK_NAMES[$i]}" == "$1" && -n "${TASK_FPATHS[$i]}" ]]; then
        slug=$(basename "${TASK_FPATHS[$i]}" .md)
        break
      fi
    done
  fi
  if [[ -z "$slug" ]]; then
    slug="task-$(date +%s)"
  fi
  echo "$slug"
}

# ---------------------------------------------------------------------------
# Folder lifecycle: move task files between todo/in-progress/done/failed
# ---------------------------------------------------------------------------
move_to_in_progress() {
  local src="$1"
  local dest="${TASK_SOURCE}/in-progress/$(basename "$src")"
  mv "$src" "$dest"
  echo "$dest"
}

move_to_done() {
  local src="$1"
  local task_branch="$2"
  local duration="$3"
  local basename_f; basename_f=$(basename "$src")
  local dest="${TASK_SOURCE}/done/${basename_f}"
  {
    echo "<!-- batch: ${BATCH_TIMESTAMP} | status: pass | duration: ${duration}s | branch: ${task_branch} -->"
    cat "$src"
  } > "$dest"
  rm -f "$src"
  # Clean up leftover failed copy from a previous attempt (e.g. auto-fix retry)
  rm -f "${TASK_SOURCE}/failed/${basename_f}"
}

move_to_failed() {
  local src="$1"
  local task_branch="$2"
  local duration="$3"
  local log_file="${4:-}"  # optional: task log for auto-fix analysis
  local dest="${TASK_SOURCE}/failed/$(basename "$src")"
  {
    echo "<!-- batch: ${BATCH_TIMESTAMP} | status: fail | duration: ${duration}s | branch: ${task_branch} -->"
    cat "$src"
  } > "$dest"
  rm -f "$src"

  # Auto-fix retry: ask AI to analyze failure and potentially retry
  if [[ "$AUTO_FIX" == true ]]; then
    auto_fix_and_retry "$dest" "$task_branch" "$log_file" &
  fi
}

# ---------------------------------------------------------------------------
# Auto-fix retry system
# Max MAX_AUTO_FIX_RETRIES attempts per task. AI analyzes the failure log,
# and if it finds a fixable issue, moves the task back to todo/.
# ---------------------------------------------------------------------------

get_retry_count() {
  local file="$1"
  local count
  count=$(grep -c '<!-- batch:.*status: fail' "$file" 2>/dev/null || echo "0")
  echo "$count"
}

auto_fix_and_retry() {
  local failed_file="$1"
  local task_branch="$2"
  local log_file="$3"
  local task_name
  task_name=$(grep -m1 '^# ' "$failed_file" 2>/dev/null | sed 's/^# //')

  local retries
  retries=$(get_retry_count "$failed_file")

  if [[ $retries -ge $MAX_AUTO_FIX_RETRIES ]]; then
    echo -e "${RED}[auto-fix] ${task_name}: max retries ($MAX_AUTO_FIX_RETRIES) reached, giving up${NC}"
    return 1
  fi

  echo -e "${YELLOW}[auto-fix] Analyzing failure for: ${task_name} (attempt ${retries}/${MAX_AUTO_FIX_RETRIES})${NC}"

  # Build context for the AI agent
  local log_tail=""
  if [[ -n "$log_file" && -f "$log_file" ]]; then
    log_tail=$(tail -100 "$log_file" 2>/dev/null)
  fi

  local analysis_prompt="A pipeline task failed. Analyze the error and decide if it's auto-fixable.

Task: ${task_name}
Branch: ${task_branch}
Retry count: ${retries}/${MAX_AUTO_FIX_RETRIES}

Error log (last 100 lines):
\`\`\`
${log_tail}
\`\`\`

Respond ONLY with one of:
1. RETRY - if the error is transient (timeout, rate limit, network) and retrying might help
2. FIX:<description> - if you can identify a specific fix needed in the pipeline/config
3. SKIP - if the error is fundamental and retrying won't help

Be concise. One line response."

  local ai_response=""

  # Try claude first, then opencode
  if command -v claude &>/dev/null; then
    ai_response=$(echo "$analysis_prompt" | claude --print 2>/dev/null | head -5 || true)
  elif command -v opencode &>/dev/null; then
    ai_response=$(cd "$REPO_ROOT" && opencode run --agent coder "$analysis_prompt" 2>/dev/null | tail -5 || true)
  fi

  if [[ -z "$ai_response" ]]; then
    echo -e "${BLUE}[auto-fix] No AI available, checking log patterns...${NC}"
    # Fallback: simple pattern matching
    if [[ -n "$log_tail" ]]; then
      if echo "$log_tail" | grep -qiE 'rate.limit|429|quota|throttl'; then
        ai_response="RETRY"
      elif echo "$log_tail" | grep -qiE 'timeout|timed.out|exit code: 124'; then
        ai_response="RETRY"
      elif echo "$log_tail" | grep -qiE 'auto-rejecting|external_directory|permission'; then
        ai_response="FIX:worktree permission issue"
      elif echo "$log_tail" | grep -qiE 'ENOENT|not found|no such file'; then
        ai_response="RETRY"
      fi
    fi
    # If the task failed in <10s, it's likely a setup issue worth retrying
    local dur
    dur=$(grep -m1 '<!-- batch:' "$failed_file" 2>/dev/null | sed 's/.*duration: \([0-9]*\)s.*/\1/' || echo "0")
    if [[ "$dur" -lt 10 && -z "$ai_response" ]]; then
      ai_response="RETRY"
    fi
  fi

  echo -e "${BLUE}[auto-fix] AI says: ${ai_response}${NC}"

  case "$ai_response" in
    RETRY*|retry*)
      echo -e "${GREEN}[auto-fix] Requeueing: ${task_name}${NC}"
      # Move back to todo (strip batch metadata to avoid accumulation)
      local todo_dest="${TASK_SOURCE}/todo/$(basename "$failed_file")"
      grep -v '^<!-- batch:' "$failed_file" > "$todo_dest"
      # Preserve retry count as a comment
      sed -i.bak "1i\\
<!-- retries: ${retries} -->" "$todo_dest"
      rm -f "${todo_dest}.bak"
      rm -f "$failed_file"
      # Delete the stale branch so it gets recreated fresh
      git -C "$REPO_ROOT" branch -D "$task_branch" 2>/dev/null || true
      ;;
    FIX:*|fix:*)
      local fix_desc="${ai_response#*:}"
      echo -e "${YELLOW}[auto-fix] Fix needed: ${fix_desc}${NC}"
      echo -e "${YELLOW}[auto-fix] Requeueing with fix note${NC}"
      local todo_dest="${TASK_SOURCE}/todo/$(basename "$failed_file")"
      {
        echo "<!-- retries: ${retries} -->"
        echo "<!-- auto-fix-note: ${fix_desc} -->"
        grep -v '^<!-- batch:' "$failed_file"
      } > "$todo_dest"
      rm -f "$failed_file"
      git -C "$REPO_ROOT" branch -D "$task_branch" 2>/dev/null || true
      ;;
    *)
      echo -e "${RED}[auto-fix] Skipping: ${task_name} (not auto-fixable)${NC}"
      ;;
  esac
}

# ---------------------------------------------------------------------------
# Build pipeline.sh arguments for a task
# ---------------------------------------------------------------------------
pipeline_args() {
  local task_name="$1"
  local task_file="$2"  # empty string for text mode
  local task_branch="$3"

  local args="--branch $task_branch"

  if [[ -n "$task_file" ]]; then
    args="$args --task-file $task_file"
  fi

  echo "$args"
}

# ---------------------------------------------------------------------------
# Sequential mode (--workers 1, default)
#
# Always uses a single git worktree to avoid polluting the main repo's branch.
# ---------------------------------------------------------------------------
run_sequential() {
  local WORKTREE_BASE="$REPO_ROOT/.pipeline-worktrees"
  local wt="$WORKTREE_BASE/worker-1"

  # Cleanup on exit
  cleanup_sequential() {
    if [[ -d "$wt" ]]; then
      git -C "$REPO_ROOT" worktree remove --force "$wt" 2>/dev/null || rm -rf "$wt"
    fi
    rmdir "$WORKTREE_BASE" 2>/dev/null || true
    # Safety: ensure main repo is on original branch
    git -C "$REPO_ROOT" checkout "$ORIGINAL_BRANCH" 2>/dev/null || true
  }
  trap cleanup_sequential EXIT

  # Create single worktree
  echo -e "${BLUE}Creating worktree for sequential worker...${NC}"
  git -C "$REPO_ROOT" worktree prune 2>/dev/null || true
  mkdir -p "$WORKTREE_BASE"
  if [[ -d "$wt" ]]; then
    git -C "$REPO_ROOT" worktree remove --force "$wt" 2>/dev/null || rm -rf "$wt"
  fi
  git -C "$REPO_ROOT" worktree add --detach "$wt" HEAD
  setup_worktree_deps "$wt"
  echo -e "  ${GREEN}worker-1${NC} → ${wt}"
  echo ""

  local pipeline_script="$wt/scripts/pipeline.sh"

  for i in "${!TASK_NAMES[@]}"; do
    local task="${TASK_NAMES[$i]}"
    local task_file="${TASK_FPATHS[$i]}"
    local task_num=$((i + 1))

    echo -e "${CYAN}══════════════════════════════════════════════════${NC}"
    echo -e "${BLUE}Task ${task_num}/${TOTAL}:${NC} ${task}"
    echo -e "${CYAN}══════════════════════════════════════════════════${NC}"

    local start_time
    start_time=$(date +%s)
    local task_branch="pipeline/$(task_slug "$task")"

    # Folder mode: move to in-progress
    local active_file="$task_file"
    if $FOLDER_MODE && [[ -n "$task_file" ]]; then
      active_file=$(move_to_in_progress "$task_file")
    fi

    # Reset worktree to HEAD before each task (clean slate)
    git -C "$wt" checkout --detach HEAD 2>/dev/null || true
    git -C "$wt" clean -fd 2>/dev/null || true

    # Copy task file into worktree if needed
    local wt_task_file=""
    if [[ -n "$active_file" ]]; then
      wt_task_file="$wt/.pipeline-task-${task_num}.md"
      cp "$active_file" "$wt_task_file"
    fi

    # Check if checkpoint exists for auto-resume
    local resume_flag=""
    local task_slug_name
    task_slug_name=$(task_slug "$task")
    if [[ -f "$REPO_ROOT/tasks/artifacts/${task_slug_name}/checkpoint.json" ]]; then
      resume_flag="--resume"
    fi

    local exit_code=0
    local log_file="$REPO_ROOT/.opencode/pipeline/logs/seq-task-${task_num}.log"
    mkdir -p "$(dirname "$log_file")"

    # shellcheck disable=SC2086
    if [[ -n "$wt_task_file" ]]; then
      "$pipeline_script" --branch "$task_branch" --task-file "$wt_task_file" $resume_flag $EXTRA_ARGS \
        > "$log_file" 2>&1 || exit_code=$?
    else
      "$pipeline_script" --branch "$task_branch" $resume_flag $EXTRA_ARGS "$task" \
        > "$log_file" 2>&1 || exit_code=$?
    fi

    rm -f "$wt_task_file"

    local end_time duration
    end_time=$(date +%s)
    duration=$(( end_time - start_time ))

    if [[ $exit_code -eq 0 ]]; then
      PASSED=$((PASSED + 1))
      echo "| ${task_num} | ${task} | ✓ PASS | ${duration}s | \`${task_branch}\` |" >> "$RESULTS_FILE"
      echo -e "${GREEN}Task ${task_num} PASSED (${duration}s)${NC}"
      if $FOLDER_MODE && [[ -n "$active_file" ]]; then
        move_to_done "$active_file" "$task_branch" "$duration"
      fi
    else
      FAILED=$((FAILED + 1))
      echo "| ${task_num} | ${task} | ✗ FAIL | ${duration}s | \`${task_branch}\` |" >> "$RESULTS_FILE"
      echo -e "${RED}Task ${task_num} FAILED (${duration}s)${NC}"
      if $FOLDER_MODE && [[ -n "$active_file" ]]; then
        move_to_failed "$active_file" "$task_branch" "$duration" "$log_file"
      fi

      if [[ "$STOP_ON_FAILURE" == true ]]; then
        echo -e "${YELLOW}Stopping batch (--no-stop-on-failure to continue)${NC}"
        STOPPED=true
        for j in $(seq $((i + 1)) $((TOTAL - 1))); do
          echo "| $((j + 1)) | ${TASK_NAMES[$j]} | — SKIP | — | — |" >> "$RESULTS_FILE"
        done
        break
      fi
    fi

    echo ""
  done
}

# ---------------------------------------------------------------------------
# Worktree dependency setup
#
# Git worktrees do NOT include .gitignored directories (vendor/, node_modules/,
# var/, .venv/). Symlink them from the main repo so agents can run tools like
# phpstan, codecept, openspec etc.
# ---------------------------------------------------------------------------
setup_worktree_deps() {
  local wt="$1"

  # Clear stale sandbox registrations in opencode's project DB.
  # Opencode registers worktrees as "sandboxes" with restricted permissions,
  # causing agents to get "external_directory; auto-rejecting" errors on file reads.
  # Removing the sandbox entries lets opencode treat each worktree as a fresh project.
  local oc_db="$HOME/.local/share/opencode/opencode.db"
  if [[ -f "$oc_db" ]] && command -v sqlite3 &>/dev/null; then
    sqlite3 "$oc_db" "UPDATE project SET sandboxes = '[]' WHERE worktree = '$(printf '%s' "$REPO_ROOT" | sed "s/'/''/g")'" 2>/dev/null || true
  fi

  # Find vendor/, node_modules/, var/ directories in the main repo (max depth 3)
  # and create matching symlinks in the worktree
  while IFS= read -r dep_dir; do
    local rel_path="${dep_dir#"$REPO_ROOT"/}"
    local wt_parent="$wt/$(dirname "$rel_path")"
    local wt_target="$wt/$rel_path"

    # Skip if symlink already exists
    [[ -L "$wt_target" ]] && continue
    # Skip if directory already exists (shouldn't happen, but safety check)
    [[ -d "$wt_target" ]] && continue

    mkdir -p "$wt_parent"
    ln -s "$dep_dir" "$wt_target"
  done < <(find "$REPO_ROOT" -maxdepth 3 -type d \( -name vendor -o -name node_modules -o -name var -o -name '.venv' \) \
    -not -path '*/.opencode/*' -not -path '*/.git/*' 2>/dev/null)

  # Symlink .local/ if it exists (Docker volume mounts, local state)
  if [[ -d "$REPO_ROOT/.local" && ! -L "$wt/.local" ]]; then
    ln -s "$REPO_ROOT/.local" "$wt/.local"
  fi

  # Symlink tasks/artifacts/ so checkpoint data is shared across worktrees
  local artifacts_dir="$REPO_ROOT/tasks/artifacts"
  mkdir -p "$artifacts_dir"
  if [[ ! -L "$wt/tasks/artifacts" ]]; then
    mkdir -p "$wt/tasks"
    ln -s "$artifacts_dir" "$wt/tasks/artifacts"
  fi
}

# ---------------------------------------------------------------------------
# Parallel mode (--workers N where N > 1)
#
# Uses git worktrees so each worker has an isolated copy of the repo.
# A FIFO-based semaphore controls how many workers run concurrently.
# ---------------------------------------------------------------------------
run_parallel() {
  # IMPORTANT: Worktrees MUST be outside .opencode/ — opencode treats paths
  # inside .opencode/ as sandbox directories with restricted file permissions,
  # causing "external_directory; auto-rejecting" errors for all agent file reads.
  local WORKTREE_BASE="$REPO_ROOT/.pipeline-worktrees"
  local TASK_RESULT_DIR
  TASK_RESULT_DIR="$(mktemp -d)"

  # FIFO for worker pool semaphore
  local POOL_FIFO
  POOL_FIFO="$(mktemp -u)"
  mkfifo "$POOL_FIFO"

  # Open FIFO bidirectionally on fd 3 (prevents blocking on open)
  exec 3<>"$POOL_FIFO"

  # Cleanup function
  cleanup_parallel() {
    exec 3>&- 2>/dev/null || true
    rm -f "$POOL_FIFO"
    rm -rf "$TASK_RESULT_DIR"
    for ((w=1; w<=WORKERS; w++)); do
      local wt="$WORKTREE_BASE/worker-${w}"
      if [[ -d "$wt" ]]; then
        git -C "$REPO_ROOT" worktree remove --force "$wt" 2>/dev/null || rm -rf "$wt"
      fi
    done
    rmdir "$WORKTREE_BASE" 2>/dev/null || true
    # Safety: ensure main repo stays on original branch
    git -C "$REPO_ROOT" checkout "$ORIGINAL_BRANCH" 2>/dev/null || true
  }
  trap cleanup_parallel EXIT

  if [[ "$STOP_ON_FAILURE" == true ]]; then
    echo -e "${YELLOW}Note: --stop-on-failure is ignored with --workers > 1${NC}"
  fi

  echo -e "${BLUE}Creating ${WORKERS} git worktrees...${NC}"

  # Prune stale worktree refs left from crashed runs
  git -C "$REPO_ROOT" worktree prune 2>/dev/null || true

  mkdir -p "$WORKTREE_BASE"

  for ((w=1; w<=WORKERS; w++)); do
    local wt="$WORKTREE_BASE/worker-${w}"
    # Force-remove old worktree (directory + git ref)
    if [[ -d "$wt" ]]; then
      git -C "$REPO_ROOT" worktree remove --force "$wt" 2>/dev/null || rm -rf "$wt"
    fi
    # Create fresh worktree from current HEAD — do NOT suppress errors
    git -C "$REPO_ROOT" worktree add --detach "$wt" HEAD
    setup_worktree_deps "$wt"
    echo -e "  ${GREEN}worker-${w}${NC} → ${wt}"
    echo "worker-${w}" >&3
  done
  echo ""

  # Launch tasks
  local pids=()

  for i in "${!TASK_NAMES[@]}"; do
    local task="${TASK_NAMES[$i]}"
    local task_file="${TASK_FPATHS[$i]}"
    local task_num=$((i + 1))
    local task_branch="pipeline/$(task_slug "$task")"

    # Folder mode: move to in-progress before dispatching
    local active_file="$task_file"
    if $FOLDER_MODE && [[ -n "$task_file" ]]; then
      active_file=$(move_to_in_progress "$task_file")
    fi

    # Block until a worker slot is available
    local worker_id
    read -r worker_id <&3

    echo -e "${BLUE}[${worker_id}] Task ${task_num}/${TOTAL}:${NC} ${task}"

    (
      local wt="$WORKTREE_BASE/${worker_id}"
      local start_time
      start_time=$(date +%s)

      local pipeline_script="$wt/scripts/pipeline.sh"

      # For folder mode, the task file path is relative to REPO_ROOT
      # Copy the task file into the worktree so pipeline.sh can find it
      local wt_task_file=""
      if [[ -n "$active_file" ]]; then
        wt_task_file="$wt/.pipeline-task-${task_num}.md"
        cp "$active_file" "$wt_task_file"
      fi

      # Check if checkpoint exists for this task (enables auto-resume)
      local resume_flag=""
      local task_slug_name
      task_slug_name=$(task_slug "$task")
      if [[ -f "$REPO_ROOT/tasks/artifacts/${task_slug_name}/checkpoint.json" ]]; then
        resume_flag="--resume"
      fi

      local exit_code=0
      if [[ -n "$wt_task_file" ]]; then
        # shellcheck disable=SC2086
        "$pipeline_script" --branch "$task_branch" --task-file "$wt_task_file" $resume_flag $EXTRA_ARGS \
          > "$TASK_RESULT_DIR/task-${task_num}.log" 2>&1 || exit_code=$?
      else
        # shellcheck disable=SC2086
        "$pipeline_script" --branch "$task_branch" $resume_flag $EXTRA_ARGS "$task" \
          > "$TASK_RESULT_DIR/task-${task_num}.log" 2>&1 || exit_code=$?
      fi

      rm -f "$wt_task_file"

      local end_time duration
      end_time=$(date +%s)
      duration=$(( end_time - start_time ))

      if [[ $exit_code -eq 0 ]]; then
        echo "PASS|${task_num}|${task}|${duration}|${task_branch}" > "$TASK_RESULT_DIR/task-${task_num}.result"
        echo -e "${GREEN}[${worker_id}] Task ${task_num} PASSED (${duration}s)${NC}"
        # Folder lifecycle — move to done
        if [[ -n "$active_file" && -f "$active_file" ]]; then
          move_to_done "$active_file" "$task_branch" "$duration"
        fi
      else
        echo "FAIL|${task_num}|${task}|${duration}|${task_branch}" > "$TASK_RESULT_DIR/task-${task_num}.result"
        echo -e "${RED}[${worker_id}] Task ${task_num} FAILED (${duration}s)${NC}"
        # Folder lifecycle — move to failed (pass log for auto-fix analysis)
        if [[ -n "$active_file" && -f "$active_file" ]]; then
          move_to_failed "$active_file" "$task_branch" "$duration" "$TASK_RESULT_DIR/task-${task_num}.log"
        fi
      fi

      # Return worker to pool
      echo "$worker_id" >&3
    ) &
    pids+=($!)
  done

  # Wait for all background tasks
  echo -e "${BLUE}Waiting for all workers to finish...${NC}"
  for pid in "${pids[@]}"; do
    wait "$pid" 2>/dev/null || true
  done

  # Aggregate results in task order
  for ((t=1; t<=TOTAL; t++)); do
    local result_file="$TASK_RESULT_DIR/task-${t}.result"
    if [[ -f "$result_file" ]]; then
      local line
      line=$(cat "$result_file")
      local status task_num task_desc duration branch
      IFS='|' read -r status task_num task_desc duration branch <<< "$line"

      if [[ "$status" == "PASS" ]]; then
        PASSED=$((PASSED + 1))
        echo "| ${task_num} | ${task_desc} | ✓ PASS | ${duration}s | \`${branch}\` |" >> "$RESULTS_FILE"
      else
        FAILED=$((FAILED + 1))
        echo "| ${task_num} | ${task_desc} | ✗ FAIL | ${duration}s | \`${branch}\` |" >> "$RESULTS_FILE"
      fi
    else
      echo "| ${t} | ${TASK_NAMES[$((t-1))]} | — ERROR | — | — |" >> "$RESULTS_FILE"
      FAILED=$((FAILED + 1))
    fi
  done

  echo ""
  echo -e "${BLUE}Worker logs: ${TASK_RESULT_DIR}${NC}"
}

# ---------------------------------------------------------------------------
# Run one round of tasks
# ---------------------------------------------------------------------------
run_round() {
  if [[ $WORKERS -gt 1 ]]; then
    run_parallel
  else
    run_sequential
  fi
}

# ---------------------------------------------------------------------------
# Re-scan todo/ for new tasks (watch mode)
# ---------------------------------------------------------------------------
rescan_tasks() {
  TASK_NAMES=()
  TASK_FPATHS=()

  if [[ -d "$TASK_SOURCE" ]]; then
    local todo_dir="$TASK_SOURCE/todo"
    [[ -d "$todo_dir" ]] || return 1

    local files=()
    while IFS= read -r -d '' f; do
      files+=("$f")
    done < <(find "$todo_dir" -maxdepth 1 -name '*.md' -print0 | sort -z)

    [[ ${#files[@]} -eq 0 ]] && return 1

    FOLDER_MODE=true
    for f in "${files[@]}"; do
      local title
      title=$(extract_title "$f")
      TASK_NAMES+=("$title")
      TASK_FPATHS+=("$f")
    done
    TOTAL=${#TASK_NAMES[@]}
    return 0
  fi
  return 1
}

# ---------------------------------------------------------------------------
# Print summary for current round
# ---------------------------------------------------------------------------
print_summary() {
  local batch_end batch_duration
  batch_end=$(date +%s)
  batch_duration=$(( batch_end - BATCH_START ))

  {
    echo ""
    echo "## Summary"
    echo ""
    echo "- **Passed**: ${PASSED}/${TOTAL}"
    echo "- **Failed**: ${FAILED}/${TOTAL}"
    if $STOPPED; then
      echo "- **Skipped**: $((TOTAL - PASSED - FAILED))/${TOTAL}"
    fi
    echo "- **Workers**: ${WORKERS}"
    echo "- **Watch mode**: ${WATCH_MODE}"
    echo "- **Total duration**: ${batch_duration}s ($(( batch_duration / 60 )) min)"
    echo "- **Completed**: $(date '+%Y-%m-%d %H:%M:%S')"
  } >> "$RESULTS_FILE"

  echo ""
  echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
  echo -e "${YELLOW}Batch Results:${NC}"
  echo -e "  ${GREEN}Passed:${NC}   ${PASSED}/${TOTAL}"
  echo -e "  ${RED}Failed:${NC}   ${FAILED}/${TOTAL}"
  if $STOPPED; then
    echo -e "  ${YELLOW}Skipped:${NC}  $((TOTAL - PASSED - FAILED))/${TOTAL}"
  fi
  echo -e "  ${BLUE}Workers:${NC}  ${WORKERS}"
  echo -e "  ${BLUE}Duration:${NC} ${batch_duration}s ($(( batch_duration / 60 )) min)"
  echo -e "  ${BLUE}Report:${NC}   ${RESULTS_FILE}"
  echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
}

# ---------------------------------------------------------------------------
# Main execution
# ---------------------------------------------------------------------------

# First round: run tasks loaded at startup
run_round
print_summary

if $WATCH_MODE && $FOLDER_MODE; then
  echo ""
  echo -e "${CYAN}Watch mode active — polling todo/ every ${WATCH_INTERVAL}s (Ctrl-C to stop)${NC}"

  while true; do
    sleep "$WATCH_INTERVAL"

    if rescan_tasks; then
      echo ""
      echo -e "${CYAN}══════════════════════════════════════════════════${NC}"
      echo -e "${YELLOW}New tasks detected: ${TOTAL}${NC}"
      echo -e "${CYAN}══════════════════════════════════════════════════${NC}"

      # Reset counters for new round
      PASSED=0
      FAILED=0
      STOPPED=false
      BATCH_START=$(date +%s)
      BATCH_TIMESTAMP=$(date +%Y%m%d_%H%M%S)
      RESULTS_FILE="$REPO_ROOT/.opencode/pipeline/reports/batch_${BATCH_TIMESTAMP}.md"
      mkdir -p "$(dirname "$RESULTS_FILE")"

      # Write new report header
      {
        echo "# Batch Pipeline Results (watch round)"
        echo ""
        echo "- **Started**: $(date '+%Y-%m-%d %H:%M:%S')"
        echo "- **Source**: ${TASK_SOURCE}/todo/ (watch mode)"
        echo "- **Total tasks**: ${TOTAL}"
        echo "- **Workers**: ${WORKERS}"
        echo ""
        echo "| # | Task | Status | Duration | Branch |"
        echo "|---|------|--------|----------|--------|"
      } > "$RESULTS_FILE"

      run_round
      print_summary
    fi
  done
fi

# Return to original branch
git -C "$REPO_ROOT" checkout "$ORIGINAL_BRANCH" 2>/dev/null || true

[[ $FAILED -eq 0 ]] || exit 1
