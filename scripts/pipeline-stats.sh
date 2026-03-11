#!/usr/bin/env bash
#
# Pipeline stats analyzer — retrospective analysis of pipeline runs.
#
# Usage:
#   ./scripts/pipeline-stats.sh                     # latest run
#   ./scripts/pipeline-stats.sh <task-slug>          # specific task
#   ./scripts/pipeline-stats.sh --list               # list all tasks
#   ./scripts/pipeline-stats.sh --list --failed      # list failed tasks
#
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
ARTIFACTS_BASE="$REPO_ROOT/tasks/artifacts"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
BOLD='\033[1m'
DIM='\033[2m'
NC='\033[0m'

show_help() {
  cat << 'HELP'
Pipeline Stats Analyzer

Usage:
  ./scripts/pipeline-stats.sh                     Show stats for latest run
  ./scripts/pipeline-stats.sh <task-slug>          Show stats for specific task
  ./scripts/pipeline-stats.sh --list               List all task runs
  ./scripts/pipeline-stats.sh --list --failed      List only failed runs

Options:
  --list          List all available task artifacts
  --failed        Filter to failed tasks only (with --list)
  -h, --help      Show this help
HELP
}

# List available task artifacts
list_tasks() {
  local filter_failed="${1:-false}"

  echo -e "${CYAN}Pipeline Task Artifacts${NC}"
  echo -e "${DIM}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
  echo ""

  printf "%-45s %-10s %-10s %-8s\n" "Task" "Status" "Duration" "Agents"
  printf "%-45s %-10s %-10s %-8s\n" "----" "------" "--------" "------"

  for checkpoint in "$ARTIFACTS_BASE"/*/checkpoint.json; do
    [[ -f "$checkpoint" ]] || continue

    local slug
    slug=$(basename "$(dirname "$checkpoint")")
    local info
    info=$(python3 -c "
import json
with open('$checkpoint', 'r') as f:
    data = json.load(f)
agents = data.get('agents', {})
total_dur = sum(a.get('duration', 0) for a in agents.values())
statuses = [a.get('status', '?') for a in agents.values()]
has_fail = any(s not in ('done',) for s in statuses)
agent_count = len(agents)
status = 'FAILED' if has_fail else 'OK'
print(f'{status}|{total_dur}|{agent_count}')
" 2>/dev/null || echo "?|0|0")

    local status duration agent_count
    IFS='|' read -r status duration agent_count <<< "$info"

    if [[ "$filter_failed" == true && "$status" != "FAILED" ]]; then
      continue
    fi

    local status_color="$GREEN"
    if [[ "$status" == "FAILED" ]]; then
      status_color="$RED"
    fi

    printf "%-45s ${status_color}%-10s${NC} %-10s %-8s\n" \
      "$slug" "$status" "${duration}s" "$agent_count"
  done
}

# Show detailed stats for a specific task
show_stats() {
  local slug="$1"
  local checkpoint="$ARTIFACTS_BASE/$slug/checkpoint.json"

  if [[ ! -f "$checkpoint" ]]; then
    echo -e "${RED}No checkpoint found for task: ${slug}${NC}"
    echo -e "${YELLOW}Available tasks:${NC}"
    ls "$ARTIFACTS_BASE" 2>/dev/null | head -20
    exit 1
  fi

  echo -e "${CYAN}Pipeline Stats: ${slug}${NC}"
  echo -e "${DIM}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
  echo ""

  # Read checkpoint data
  python3 -c "
import json
with open('$checkpoint', 'r') as f:
    data = json.load(f)
print(f\"Task: {data.get('task', '?')[:80]}\")
print(f\"Branch: {data.get('branch', '?')}\")
print(f\"Started: {data.get('started', '?')}\")
print()
" 2>/dev/null

  echo -e "${BOLD}Agent Breakdown:${NC}"
  echo ""
  printf "%-14s %-10s %-10s %-12s %-12s %-12s %-12s\n" "Agent" "Status" "Duration" "Input Tok" "Output Tok" "Cache Read" "Cache Write"
  printf "%-14s %-10s %-10s %-12s %-12s %-12s %-12s\n" "-----" "------" "--------" "---------" "----------" "----------" "-----------"

  python3 -c "
import json
with open('$checkpoint', 'r') as f:
    data = json.load(f)
agents = data.get('agents', {})
total_in = 0
total_out = 0
total_cache_r = 0
total_cache_w = 0
total_dur = 0
for name, info in agents.items():
    status = info.get('status', '?')
    dur = info.get('duration', 0)
    tokens = info.get('tokens', {})
    in_tok = tokens.get('input_tokens', 0)
    out_tok = tokens.get('output_tokens', 0)
    cache_r = tokens.get('cache_read', 0)
    cache_w = tokens.get('cache_write', 0)
    total_in += in_tok
    total_out += out_tok
    total_cache_r += cache_r
    total_cache_w += cache_w
    total_dur += dur
    icon = '✓' if status == 'done' else '✗'
    print(f'{name:<14} {icon} {status:<8} {dur}s{\"\":>{8-len(str(dur))}} {in_tok:<12} {out_tok:<12} {cache_r:<12} {cache_w:<12}')
print()
print(f'{\"TOTAL\":<14} {\"\":<10} {total_dur}s{\"\":>{8-len(str(total_dur))}} {total_in:<12} {total_out:<12} {total_cache_r:<12} {total_cache_w:<12}')
grand_total = total_in + total_out + total_cache_r + total_cache_w
print(f'\nGrand total tokens: {grand_total:,}')
" 2>/dev/null

  echo ""

  # Check for meta.json sidecars
  local log_dir="$REPO_ROOT/.opencode/pipeline/logs"
  local meta_files
  meta_files=$(ls "$log_dir"/*_*.meta.json 2>/dev/null | head -20 || true)
  if [[ -n "$meta_files" ]]; then
    echo -e "${BOLD}Agent Metadata (from sidecars):${NC}"
    echo ""
    for meta in $meta_files; do
      local agent_name log_bytes log_lines
      agent_name=$(jq -r '.agent' "$meta" 2>/dev/null || echo "?")
      log_bytes=$(jq -r '.log_bytes' "$meta" 2>/dev/null || echo 0)
      log_lines=$(jq -r '.log_lines' "$meta" 2>/dev/null || echo 0)
      echo -e "  ${BLUE}${agent_name}${NC}: ${log_lines} log lines, ${log_bytes} bytes"
    done
    echo ""
  fi
}

# Find latest task slug
find_latest_slug() {
  local latest=""
  local latest_time=0
  for checkpoint in "$ARTIFACTS_BASE"/*/checkpoint.json; do
    [[ -f "$checkpoint" ]] || continue
    local mtime
    mtime=$(stat -f %m "$checkpoint" 2>/dev/null || stat -c %Y "$checkpoint" 2>/dev/null || echo 0)
    if [[ "$mtime" -gt "$latest_time" ]]; then
      latest_time=$mtime
      latest=$(basename "$(dirname "$checkpoint")")
    fi
  done
  echo "$latest"
}

# Parse arguments
LIST_MODE=false
FILTER_FAILED=false
TASK_SLUG=""

while [[ $# -gt 0 ]]; do
  case "$1" in
    --list) LIST_MODE=true; shift ;;
    --failed) FILTER_FAILED=true; shift ;;
    --help|-h) show_help; exit 0 ;;
    *) TASK_SLUG="$1"; shift ;;
  esac
done

if [[ "$LIST_MODE" == true ]]; then
  list_tasks "$FILTER_FAILED"
  exit 0
fi

if [[ -z "$TASK_SLUG" ]]; then
  TASK_SLUG=$(find_latest_slug)
  if [[ -z "$TASK_SLUG" ]]; then
    echo -e "${YELLOW}No pipeline artifacts found.${NC}"
    echo "Run a pipeline first or specify a task slug."
    exit 1
  fi
  echo -e "${DIM}(Using latest task: ${TASK_SLUG})${NC}"
  echo ""
fi

show_stats "$TASK_SLUG"
