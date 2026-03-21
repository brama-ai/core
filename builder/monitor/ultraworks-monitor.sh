#!/usr/bin/env bash
# Ultraworks (Sisyphus) Pipeline Monitor
# Shows current state and allows launching OpenCode in tmux

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
PIPELINE_DIR="$PROJECT_ROOT/.opencode/pipeline"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'
ULTRAWORKS_MAX_RUNTIME="${ULTRAWORKS_MAX_RUNTIME:-7200}"
ULTRAWORKS_STALL_TIMEOUT="${ULTRAWORKS_STALL_TIMEOUT:-900}"
ULTRAWORKS_WATCHDOG_INTERVAL="${ULTRAWORKS_WATCHDOG_INTERVAL:-30}"

# Helper functions
print_header() {
    echo -e "${CYAN}╔══════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${CYAN}║       Ultraworks (Sisyphus) Pipeline Monitor                 ║${NC}"
    echo -e "${CYAN}╚══════════════════════════════════════════════════════════════╝${NC}"
}

print_status() {
    local label="$1"
    local value="$2"
    printf "${BLUE}%-20s${NC} %s\n" "$label:" "$value"
}

get_current_phase() {
    if [[ ! -f "$PIPELINE_DIR/handoff.md" ]]; then
        echo "idle"
        return
    fi
    
    local last_section=$(grep -E "^## " "$PIPELINE_DIR/handoff.md" | tail -1 | sed 's/^## //')
    if [[ -z "$last_section" ]]; then
        echo "idle"
    else
        echo "$last_section"
    fi
}

get_plan_info() {
    if [[ ! -f "$PIPELINE_DIR/plan.json" ]]; then
        echo "{}"
        return
    fi
    cat "$PIPELINE_DIR/plan.json"
}

get_latest_report() {
    local latest=$(ls -t "$PIPELINE_DIR/reports"/*.md 2>/dev/null | head -1)
    if [[ -n "$latest" ]]; then
        echo "$latest"
    fi
}

get_latest_summary() {
    local latest=$(ls -t "$PROJECT_ROOT/builder/tasks/summary"/*.md 2>/dev/null | head -1)
    if [[ -n "$latest" ]]; then
        echo "$latest"
    fi
}

list_pending_tasks() {
    # Check for pending tasks in builder/tasks/todo
    if [[ -d "$PROJECT_ROOT/builder/tasks/todo" ]]; then
        ls -1 "$PROJECT_ROOT/builder/tasks/todo"/*.md 2>/dev/null | head -10 || true
    fi
}

_format_duration() {
    local secs="$1"
    if (( secs >= 3600 )); then
        printf '%dh%02dm' $((secs/3600)) $((secs%3600/60))
    elif (( secs >= 60 )); then
        printf '%dm%02ds' $((secs/60)) $((secs%60))
    else
        printf '%ds' "$secs"
    fi
}

_show_live_status() {
    local has_tmux=false
    local has_process=false
    local opencode_pid=""

    # 1. Check tmux session
    if command -v tmux &>/dev/null && tmux has-session -t ultraworks 2>/dev/null; then
        has_tmux=true
    fi

    # 2. Check for opencode process
    opencode_pid=$(pgrep -f "opencode run.*auto" 2>/dev/null | head -1 || true)
    [[ -n "$opencode_pid" ]] && has_process=true

    # 3. Find latest active log (most recent .log in pipeline/logs)
    local log_dir="$PIPELINE_DIR/logs"
    local latest_log=""
    latest_log=$(ls -t "$log_dir"/task-*.log 2>/dev/null | head -1 || true)

    local now; now=$(date +%s)

    if [[ "$has_tmux" == true && "$has_process" == true ]]; then
        # Running — check log health
        local log_health="alive"
        local log_idle=0
        local log_size=0
        local log_mtime=0
        local started_info=""

        if [[ -n "$latest_log" ]]; then
            log_size=$(wc -c < "$latest_log" 2>/dev/null | tr -d ' ')
            log_mtime=$(stat -c %Y "$latest_log" 2>/dev/null || echo "$now")
            log_idle=$(( now - log_mtime ))

            # Estimate start time from log filename: task-YYYYMMDD_HHMMSS-slug.log
            local fname; fname=$(basename "$latest_log" .log)
            if [[ "$fname" =~ task-([0-9]{8}_[0-9]{6})- ]]; then
                local ts="${BASH_REMATCH[1]}"
                local dt="${ts:0:4}-${ts:4:2}-${ts:6:2} ${ts:9:2}:${ts:11:2}:${ts:13:2}"
                local start_epoch
                start_epoch=$(date -d "$dt" +%s 2>/dev/null || echo 0)
                if (( start_epoch > 0 )); then
                    local elapsed=$(( now - start_epoch ))
                    started_info=" elapsed $(_format_duration "$elapsed")"
                fi
            fi

            if (( log_idle > 300 )); then
                log_health="stale"
            elif (( log_idle > 60 )); then
                log_health="idle"
            fi
        fi

        local log_kb=$(( log_size / 1024 ))

        case "$log_health" in
            alive)
                echo -e "${GREEN}  ● RUNNING${NC}  pid=$opencode_pid  log=${log_kb}KB${started_info}"
                ;;
            idle)
                echo -e "${YELLOW}  ◌ RUNNING (idle ${log_idle}s)${NC}  pid=$opencode_pid  log=${log_kb}KB${started_info}"
                ;;
            stale)
                echo -e "${RED}  ⏳ RUNNING (no activity ${log_idle}s)${NC}  pid=$opencode_pid  log=${log_kb}KB${started_info}"
                echo -e "${RED}    Process may be stuck. Check: tmux attach -t ultraworks${NC}"
                ;;
        esac
    elif [[ "$has_tmux" == true && "$has_process" == false ]]; then
        echo -e "${RED}  ✗ DEAD${NC}  tmux session exists but opencode process not found"
        echo -e "${RED}    Session likely crashed. Kill: tmux kill-session -t ultraworks${NC}"
    elif [[ "$has_tmux" == false && "$has_process" == true ]]; then
        echo -e "${YELLOW}  ? DETACHED${NC}  opencode pid=$opencode_pid running without tmux session"
    else
        # Neither tmux nor process — check if there's a recent log that might indicate recent completion
        if [[ -n "$latest_log" ]]; then
            local log_mtime
            log_mtime=$(stat -c %Y "$latest_log" 2>/dev/null || echo 0)
            local age=$(( now - log_mtime ))
            if (( age < 300 )); then
                # Log was active in last 5 min — task probably just finished
                local tail_status="unknown"
                if tail -5 "$latest_log" 2>/dev/null | grep -q "Pipeline finished"; then
                    tail_status="completed"
                elif tail -20 "$latest_log" 2>/dev/null | grep -qi "error\|failed\|exception"; then
                    tail_status="failed"
                fi
                echo -e "${CYAN}  ■ FINISHED${NC}  ($tail_status, ${age}s ago)  $(basename "$latest_log")"
            else
                print_status "Task status" "idle (no active session)"
            fi
        else
            print_status "Task status" "idle (no active session)"
        fi
    fi
    echo ""
}

show_state() {
    print_header
    echo ""

    print_status "Project root" "$PROJECT_ROOT"

    # ── Live task status ──
    _show_live_status

    # Current phase
    local phase=$(get_current_phase)
    print_status "Current phase" "$phase"

    # Plan info
    if [[ -f "$PIPELINE_DIR/plan.json" ]]; then
        local profile=$(jq -r '.profile // "unknown"' "$PIPELINE_DIR/plan.json" 2>/dev/null || echo "unknown")
        local agents=$(jq -r '.agents | join(", ") // "none"' "$PIPELINE_DIR/plan.json" 2>/dev/null || echo "none")
        print_status "Profile" "$profile"
        print_status "Agents" "$agents"
    fi

    # Latest report
    local latest_report=$(get_latest_report)
    if [[ -n "$latest_report" ]]; then
        local report_time=$(stat -c %y "$latest_report" 2>/dev/null | cut -d. -f1)
        print_status "Latest report" "$(basename $latest_report) ($report_time)"
    fi

    local latest_summary=$(get_latest_summary)
    if [[ -n "$latest_summary" ]]; then
        local summary_time=$(stat -c %y "$latest_summary" 2>/dev/null | cut -d. -f1)
        print_status "Latest summary" "$(basename $latest_summary) ($summary_time)"
    fi

    echo ""
    echo -e "${YELLOW}─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─${NC}"
    echo ""

    # Show handoff state
    if [[ -f "$PIPELINE_DIR/handoff.md" ]]; then
        echo -e "${GREEN}Handoff state:${NC}"
        echo -e "${BLUE}─────────────────${NC}"
        head -40 "$PIPELINE_DIR/handoff.md"
        echo ""
    fi
    
    # Show pending tasks
    local pending=$(list_pending_tasks)
    if [[ -n "$pending" ]]; then
        echo -e "${YELLOW}Pending tasks in builder/tasks/todo:${NC}"
        echo "$pending" | while read task; do
            local name=$(basename "$task" .md)
            local priority=$(grep -m1 "<!-- priority:" "$task" 2>/dev/null | sed 's/.*priority: *\([0-9]*\).*/\1/' || echo "1")
            echo "  [$priority] $name"
        done
        echo ""
    fi
    
    # Recent reports
    echo -e "${YELLOW}Recent reports:${NC}"
    ls -lt "$PIPELINE_DIR/reports"/*.md 2>/dev/null | head -5 | while read _ _ _ _ _ date time _ file; do
        echo "  $date $time $(basename $file)"
    done || echo "  (no reports)"
}

launch_opencode_tmux() {
    local session_name="ultraworks"
    local task_description="${1:-}"

    # Check if tmux is available
    if ! command -v tmux &> /dev/null; then
        echo -e "${RED}Error: tmux is not installed${NC}"
        echo "Install: sudo apt install tmux"
        return 1
    fi

    # Check if opencode is available
    if ! command -v opencode &> /dev/null; then
        echo -e "${RED}Error: opencode is not installed${NC}"
        return 1
    fi

    # Check if session exists
    if tmux has-session -t "$session_name" 2>/dev/null; then
        echo -e "${YELLOW}Session '$session_name' already exists${NC}"
        echo -e "Attach: ${CYAN}tmux attach -t $session_name${NC}"

        # Offer to send task
        if [[ -n "$task_description" ]]; then
            read -p "Send task to existing session? [y/N] " -n1 -r
            echo
            if [[ $REPLY =~ ^[Yy]$ ]]; then
                # Kill existing and relaunch with new task
                tmux kill-session -t "$session_name"
                _launch_opencode_session "$session_name" "$task_description"
            fi
        fi
        return 0
    fi

    _launch_opencode_session "$session_name" "$task_description"
}

_detect_model() {
    # Model routing rules for Sisyphus orchestrator:
    # Both GLM-5 and GPT-5.4 work after builder-agent Sisyphus exception fix.
    # GLM-5 first as primary (free), GPT-5.4 as strong fallback.
    # See: docs/guides/pipeline-models/ for full policy
    local models=(
        "opencode-go/glm-5"
        "openai/gpt-5.4"
        "minimax/MiniMax-M2.7"
        "opencode/big-pickle"
        "google/gemini-3.1-pro-preview"
        "opencode/minimax-m2.5-free"
        "openrouter/free"
        "openrouter/deepseek/deepseek-r1-0528:free"
    )
    local available
    available=$(opencode models 2>/dev/null)

    for model in "${models[@]}"; do
        if echo "$available" | grep -qF "$model"; then
            echo "$model"
            return 0
        fi
    done

    # Fallback to default
    echo ""
    return 1
}

_task_log_path() {
    local timestamp
    timestamp=$(date +%Y%m%d_%H%M%S)
    local task_text="${1:-unknown}"
    local slug
    slug=$(python3 - "$task_text" <<'PYEOF'
import re
import sys

text = sys.argv[1]
title = ""
for line in text.splitlines():
    stripped = line.strip()
    if stripped.startswith("# "):
        title = stripped[2:].strip()
        break
    if stripped and not stripped.startswith("<!--"):
        title = stripped
        break

if not title:
    title = "unknown"

slug = re.sub(r"[^a-z0-9]+", "-", title.lower()).strip("-")
print((slug or "unknown")[:60])
PYEOF
)
    local log_dir="$PIPELINE_DIR/logs"
    mkdir -p "$log_dir"
    echo "$log_dir/task-${timestamp}-${slug}.log"
}

_create_pr() {
    local log_file="${1:-}"
    local branch
    branch=$(git -C "$PROJECT_ROOT" rev-parse --abbrev-ref HEAD 2>/dev/null || echo "")

    if [[ -z "$branch" || "$branch" == "main" || "$branch" == "HEAD" ]]; then
        echo "Skipping PR — not on a feature branch" | tee -a "${log_file:-/dev/null}"
        return 0
    fi

    local pr_title
    pr_title=$(echo "$branch" | sed 's|^pipeline/||' | sed 's/-/ /g' | cut -c1-70)

    # Use latest summary as PR body
    local pr_body="Pipeline completed on branch: $branch"
    local summary_file
    summary_file=$(ls -t "$PROJECT_ROOT/builder/tasks/summary"/*.md 2>/dev/null | head -1)
    if [[ -n "$summary_file" && -f "$summary_file" ]]; then
        pr_body=$(cat "$summary_file")
    fi

    echo "Creating Pull Request for $branch..." | tee -a "${log_file:-/dev/null}"

    if git -C "$PROJECT_ROOT" push -u origin "$branch" 2>/dev/null; then
        local pr_url
        pr_url=$(cd "$PROJECT_ROOT" && gh pr create \
            --base main \
            --head "$branch" \
            --title "[pipeline] ${pr_title}" \
            --body "$pr_body" 2>/dev/null || true)

        if [[ -n "$pr_url" ]]; then
            echo "PR created: $pr_url" | tee -a "${log_file:-/dev/null}"
        else
            echo "PR creation failed (branch pushed)" | tee -a "${log_file:-/dev/null}"
        fi
    else
        echo "Git push failed — PR not created" | tee -a "${log_file:-/dev/null}"
    fi
}

_postprocess_summary_cmd() {
    local start_epoch="$1"
    printf '%q' "./builder/normalize-summary.py"
    printf ' --workflow ultraworks --since-epoch %q || true' "$start_epoch"
}

_timeout_prefix() {
    if command -v timeout &>/dev/null && [[ "$ULTRAWORKS_MAX_RUNTIME" =~ ^[0-9]+$ ]] && (( ULTRAWORKS_MAX_RUNTIME > 0 )); then
        printf 'timeout %q ' "$ULTRAWORKS_MAX_RUNTIME"
    fi
}

_watchdog_marker_path() {
    local log_file="$1"
    echo "${log_file}.watchdog"
}

_start_watchdog() {
    local pipeline_pid="$1"
    local log_file="$2"
    local marker_file
    marker_file=$(_watchdog_marker_path "$log_file")

    rm -f "$marker_file"

    if ! [[ "$ULTRAWORKS_STALL_TIMEOUT" =~ ^[0-9]+$ ]] || (( ULTRAWORKS_STALL_TIMEOUT <= 0 )); then
        echo ""
        return 0
    fi

    (
        local last_log_size=0
        local last_log_progress
        local last_handoff_progress
        last_log_progress=$(date +%s)
        last_handoff_progress=$(date +%s)
        local last_handoff_mtime=0

        while kill -0 "$pipeline_pid" 2>/dev/null; do
            sleep "$ULTRAWORKS_WATCHDOG_INTERVAL"

            local now
            now=$(date +%s)
            local log_size=0
            if [[ -f "$log_file" ]]; then
                log_size=$(wc -c < "$log_file" 2>/dev/null || echo 0)
            fi
            if (( log_size > last_log_size )); then
                last_log_size="$log_size"
                last_log_progress="$now"
            fi

            local handoff_mtime=0
            if [[ -f "$PIPELINE_DIR/handoff.md" ]]; then
                handoff_mtime=$(stat -c %Y "$PIPELINE_DIR/handoff.md" 2>/dev/null || echo 0)
            fi
            if (( handoff_mtime > last_handoff_mtime )); then
                last_handoff_mtime="$handoff_mtime"
                last_handoff_progress="$now"
            fi

            local log_idle=$(( now - last_log_progress ))
            local handoff_idle=$(( now - last_handoff_progress ))
            if (( log_idle >= ULTRAWORKS_STALL_TIMEOUT && handoff_idle >= ULTRAWORKS_STALL_TIMEOUT )); then
                printf 'stall:%ss\n' "$ULTRAWORKS_STALL_TIMEOUT" > "$marker_file"
                echo "Ultraworks watchdog: no log or handoff progress for ${ULTRAWORKS_STALL_TIMEOUT}s, terminating pipeline." | tee -a "$log_file"
                kill -TERM "$pipeline_pid" 2>/dev/null || true
                sleep 10
                kill -KILL "$pipeline_pid" 2>/dev/null || true
                exit 0
            fi
        done
    ) &

    echo "$!"
}

_stop_watchdog() {
    local watchdog_pid="${1:-}"
    [[ -z "$watchdog_pid" ]] && return 0
    kill "$watchdog_pid" 2>/dev/null || true
    wait "$watchdog_pid" 2>/dev/null || true
}

_run_headless_pipeline() {
    local task="$1"
    local model="$2"
    local log_file="$3"
    local start_epoch="$4"

    local -a run_cmd=(opencode run --command auto "$task")
    if [[ -n "$model" ]]; then
        run_cmd=(opencode run --model "$model" --command auto "$task")
    fi

    local pipeline_pid=""
    if command -v timeout &>/dev/null && [[ "$ULTRAWORKS_MAX_RUNTIME" =~ ^[0-9]+$ ]] && (( ULTRAWORKS_MAX_RUNTIME > 0 )); then
        timeout "$ULTRAWORKS_MAX_RUNTIME" "${run_cmd[@]}" > >(tee "$log_file") 2>&1 &
    else
        "${run_cmd[@]}" > >(tee "$log_file") 2>&1 &
    fi
    pipeline_pid=$!

    local watchdog_pid=""
    watchdog_pid=$(_start_watchdog "$pipeline_pid" "$log_file")

    local pipeline_status=0
    set +e
    wait "$pipeline_pid"
    pipeline_status=$?
    set -e

    _stop_watchdog "$watchdog_pid"

    local marker_file
    marker_file=$(_watchdog_marker_path "$log_file")
    if [[ -f "$marker_file" ]]; then
        echo "Ultraworks pipeline stopped by watchdog ($(cat "$marker_file"))." | tee -a "$log_file"
        rm -f "$marker_file"
    elif [[ "$pipeline_status" -eq 124 || "$pipeline_status" -eq 137 ]]; then
        echo "Ultraworks wrapper timeout after ${ULTRAWORKS_MAX_RUNTIME}s" | tee -a "$log_file"
    fi

    ./builder/postmortem-summary.sh 2>&1 | tee -a "$log_file" || true
    ./builder/normalize-summary.py --workflow ultraworks --since-epoch "$start_epoch" 2>&1 | tee -a "$log_file" || true

    # Create PR on success
    if [[ "$pipeline_status" -eq 0 ]]; then
        _create_pr "$log_file" || true
    fi

    return "$pipeline_status"
}

_launch_opencode_session() {
    local session_name="$1"
    local task_description="${2:-}"
    local start_epoch
    start_epoch=$(date +%s)

    # Detect best available model for Sisyphus orchestration
    local model
    model=$(_detect_model)
    local model_flag=""
    if [[ -n "$model" ]]; then
        model_flag="--model $model"
        echo -e "${BLUE}Model:${NC} $model"
    fi

    echo -e "${GREEN}Starting Sisyphus pipeline in tmux session '$session_name'${NC}"

    if [[ -n "$task_description" ]]; then
        # Generate log file path
        local log_file
        log_file=$(_task_log_path "$task_description")
        echo -e "${BLUE}Log:${NC} $log_file"

        local runner
        printf -v runner '%q %q %q' "$SCRIPT_DIR/ultraworks-monitor.sh" headless "$task_description"
        tmux new-session -d -s "$session_name" -c "$PROJECT_ROOT" \
            "bash -lc '$runner; status=\$?; echo; echo \"Pipeline finished with status \$status. Press Enter to close.\"; read; exit \$status'"
        echo -e "${CYAN}Pipeline running. Attach: tmux attach -t $session_name${NC}"
    else
        # Interactive mode: just start opencode TUI (no logging)
        tmux new-session -d -s "$session_name" -c "$PROJECT_ROOT" \
            "opencode $model_flag"
        echo -e "${CYAN}OpenCode TUI started. Attach: tmux attach -t $session_name${NC}"
    fi
}

interactive_menu() {
    while true; do
        echo ""
        echo -e "${CYAN}Actions:${NC}"
        echo "  1) Show current state"
        echo "  2) Launch OpenCode (tmux)"
        echo "  3) View latest report"
        echo "  4) View latest summary"
        echo "  5) View handoff"
        echo "  6) Tail logs"
        echo "  q) Quit"
        echo ""
        read -p "Choose [1-6/q]: " -n1 -r
        echo ""
        
        case $REPLY in
            1) show_state ;;
            2) launch_opencode_tmux ;;
            3) 
                local report=$(get_latest_report)
                if [[ -n "$report" ]]; then
                    less "$report"
                else
                    echo -e "${YELLOW}No reports available${NC}"
                fi
                ;;
            4)
                local summary=$(get_latest_summary)
                if [[ -n "$summary" ]]; then
                    less "$summary"
                else
                    echo -e "${YELLOW}No summary available${NC}"
                fi
                ;;
            5)
                if [[ -f "$PIPELINE_DIR/handoff.md" ]]; then
                    less "$PIPELINE_DIR/handoff.md"
                else
                    echo -e "${YELLOW}No handoff available${NC}"
                fi
                ;;
            6)
                local log_dir="$PIPELINE_DIR/logs"
                if [[ -d "$log_dir" ]]; then
                    ls -lt "$log_dir"/*.log 2>/dev/null | head -1 | awk '{print $NF}' | xargs tail -f || echo "No logs"
                else
                    echo -e "${YELLOW}No logs available${NC}"
                fi
                ;;
            q|Q) exit 0 ;;
            *) echo -e "${RED}Invalid option${NC}" ;;
        esac
    done
}

# ═══════════════════════════════════════════════════════════════════════
# TUI Watch Mode — split-panel live monitor
# Left 2/3: main content (task info, handoff, logs)
# Right 1/3: agent progress sidebar
# ═══════════════════════════════════════════════════════════════════════

_tui_term_size() {
    TUI_ROWS=$(tput lines 2>/dev/null || echo 24)
    TUI_COLS=$(tput cols 2>/dev/null || echo 80)
}

# Parse agent list from plan.json
_tui_get_agents() {
    if [[ -f "$PIPELINE_DIR/plan.json" ]]; then
        jq -r '.agents[]' "$PIPELINE_DIR/plan.json" 2>/dev/null
    fi
}

# Parse agent statuses from handoff.md
# Returns: agent_name|status lines
_tui_get_agent_statuses() {
    local handoff="$1"
    [[ -f "$handoff" ]] || return
    local current_agent=""
    while IFS= read -r line; do
        if [[ "$line" =~ ^##[[:space:]]+(.+)$ ]]; then
            current_agent="${BASH_REMATCH[1]}"
            # Normalize to lowercase
            current_agent=$(echo "$current_agent" | tr '[:upper:]' '[:lower:]')
        elif [[ -n "$current_agent" && "$line" =~ \*\*Status\*\*:[[:space:]]*(.+) ]]; then
            local status="${BASH_REMATCH[1]}"
            echo "${current_agent}|${status}"
            current_agent=""
        fi
    done < "$handoff"
}

# Find the active handoff file (most recent handoff-*.md or handoff.md)
_tui_find_handoff() {
    # Prefer timestamped handoff files, fallback to handoff.md
    local latest
    latest=$(ls -t "$PIPELINE_DIR"/handoff-*.md 2>/dev/null | head -1)
    if [[ -n "$latest" ]]; then
        echo "$latest"
    elif [[ -f "$PIPELINE_DIR/handoff.md" ]]; then
        echo "$PIPELINE_DIR/handoff.md"
    fi
}

# Render right sidebar content into SIDEBAR_LINES array
_tui_build_sidebar() {
    SIDEBAR_LINES=()
    local sidebar_w="$1"
    local inner_w=$((sidebar_w - 3))  # padding + border

    # Decide mode: active task → agent checklist, idle → summaries
    local has_tmux=false has_process=false
    if command -v tmux &>/dev/null && tmux has-session -t ultraworks 2>/dev/null; then has_tmux=true; fi
    if pgrep -f "opencode run.*auto" &>/dev/null; then has_process=true; fi

    if [[ "$has_tmux" == true || "$has_process" == true ]]; then
        _tui_build_sidebar_agents "$sidebar_w" "$has_process"
    else
        _tui_build_sidebar_summaries "$sidebar_w"
    fi
}

# Sidebar: agent checklist (when task is active)
_tui_build_sidebar_agents() {
    local sidebar_w="$1"
    local has_process="$2"
    local inner_w=$((sidebar_w - 3))

    # Header
    SIDEBAR_LINES+=("$(printf " ${CYAN}%-${inner_w}s${NC}" "Agents")")
    SIDEBAR_LINES+=("$(printf " ${CYAN}%s${NC}" "$(printf '%*s' "$inner_w" '' | tr ' ' '─')")")

    # Get agents and their statuses
    local -A agent_status
    local handoff
    handoff=$(_tui_find_handoff)
    if [[ -n "$handoff" ]]; then
        while IFS='|' read -r name status; do
            agent_status["$name"]="$status"
        done < <(_tui_get_agent_statuses "$handoff")
    fi

    local agents=()
    while IFS= read -r a; do
        [[ -n "$a" ]] && agents+=("$a")
    done < <(_tui_get_agents)

    if [[ ${#agents[@]} -eq 0 ]]; then
        SIDEBAR_LINES+=("$(printf " ${NC}%-${inner_w}s${NC}" "(no plan)")")
        _tui_build_sidebar_status "$inner_w"
        return
    fi

    local now; now=$(date +%s)
    local spin_chars='⠋⠙⠹⠸⠼⠴⠦⠧⠇⠏'
    local spin_idx=$(( now % 10 ))
    local spinner="${spin_chars:$spin_idx:1}"

    # Pipeline is sequential: find the last completed/failed agent index.
    # All agents before it are implicitly done (even if handoff says "pending").
    local last_done_idx=-1
    local idx=0
    for agent in "${agents[@]}"; do
        local st="${agent_status[$agent]:-pending}"
        case "$st" in
            completed|done|failed|error|skipped) last_done_idx=$idx ;;
        esac
        idx=$((idx + 1))
    done

    local found_running=false
    idx=0
    for agent in "${agents[@]}"; do
        local st="${agent_status[$agent]:-pending}"
        local icon label color
        local agent_display="$agent"
        # Capitalize first letter for display
        agent_display="$(echo "${agent:0:1}" | tr '[:lower:]' '[:upper:]')${agent:1}"

        case "$st" in
            completed|done)
                icon="✓"
                color="$GREEN"
                label="done"
                ;;
            failed|error)
                icon="✗"
                color="$RED"
                label="fail"
                ;;
            skipped)
                icon="–"
                color="$YELLOW"
                label="skip"
                ;;
            *)
                if (( idx < last_done_idx )); then
                    # Before the last completed agent — implicitly done
                    icon="✓"
                    color="$GREEN"
                    label="done"
                elif [[ "$found_running" == false && "$has_process" == true ]]; then
                    # First non-completed agent while process is running = active
                    icon="$spinner"
                    color="$YELLOW"
                    label="running"
                    found_running=true
                else
                    icon="○"
                    color="$NC"
                    label=""
                fi
                ;;
        esac

        local line
        if [[ -n "$label" ]]; then
            line=$(printf " %b%s%b %-*s %b%s%b" "$color" "$icon" "$NC" $((inner_w - 10)) "$agent_display" "$color" "$label" "$NC")
        else
            line=$(printf " %b%s%b %-*s" "$color" "$icon" "$NC" $((inner_w - 3)) "$agent_display")
        fi
        SIDEBAR_LINES+=("$line")
        idx=$((idx + 1))
    done

    # Task elapsed time at bottom
    SIDEBAR_LINES+=("$(printf " ${CYAN}%s${NC}" "$(printf '%*s' "$inner_w" '' | tr ' ' '─')")")
    if [[ -n "$handoff" ]]; then
        local started_str
        started_str=$(grep -oP '(?<=\*\*Started\*\*: ).*' "$handoff" 2>/dev/null | head -1)
        if [[ -n "$started_str" ]]; then
            local started_epoch
            started_epoch=$(date -d "$started_str" +%s 2>/dev/null || echo 0)
            if (( started_epoch > 0 )); then
                local elapsed=$(( now - started_epoch ))
                SIDEBAR_LINES+=("$(printf " %-${inner_w}s" "⏱ $(_format_duration "$elapsed")")")
            fi
        fi
        local task_name
        task_name=$(grep -oP '(?<=\*\*Task\*\*: ).*' "$handoff" 2>/dev/null | head -1)
        if [[ -n "$task_name" ]]; then
            if (( ${#task_name} > inner_w - 1 )); then
                task_name="${task_name:0:$((inner_w - 4))}..."
            fi
            SIDEBAR_LINES+=("$(printf " ${NC}%-${inner_w}s${NC}" "$task_name")")
        fi
    fi

    # Live status indicator
    _tui_build_sidebar_status "$inner_w"
}

# Sidebar: recent summaries (when idle)
_tui_build_sidebar_summaries() {
    local sidebar_w="$1"
    local inner_w=$((sidebar_w - 3))

    SIDEBAR_LINES+=("$(printf " ${CYAN}%-${inner_w}s${NC}" "Recent Runs")")
    SIDEBAR_LINES+=("$(printf " ${CYAN}%s${NC}" "$(printf '%*s' "$inner_w" '' | tr ' ' '─')")")

    # Collect summaries from builder/tasks/summary/ and done/
    local -a entries=()

    # Summaries
    local f
    for f in $(ls -t "$PROJECT_ROOT/builder/tasks/summary"/*.md 2>/dev/null | head -10); do
        local name; name=$(basename "$f" .md)
        local mtime; mtime=$(stat -c %Y "$f" 2>/dev/null || echo 0)
        # Extract status from first lines
        local status="—"
        local st_line
        st_line=$(grep -m1 '^\*\*Статус:\*\*\|^\*\*Status:\*\*' "$f" 2>/dev/null || true)
        if echo "$st_line" | grep -qi "pass\|done\|success"; then
            status="PASS"
        elif echo "$st_line" | grep -qi "fail"; then
            status="FAIL"
        fi
        # Extract workflow
        local workflow="—"
        local wf_line
        wf_line=$(grep -m1 '^\*\*Workflow:\*\*' "$f" 2>/dev/null || true)
        if [[ -n "$wf_line" ]]; then
            workflow=$(echo "$wf_line" | sed 's/.*\*\*Workflow:\*\* *//')
        fi
        entries+=("${mtime}|summary|${status}|${workflow}|${name}|${f}")
    done

    # Done tasks
    for f in $(ls -t "$PROJECT_ROOT/builder/tasks/done"/*.md 2>/dev/null | head -10); do
        local name; name=$(basename "$f" .md)
        local mtime; mtime=$(stat -c %Y "$f" 2>/dev/null || echo 0)
        local status="PASS"
        local meta_line
        meta_line=$(head -1 "$f" 2>/dev/null || true)
        if echo "$meta_line" | grep -q "status: fail"; then
            status="FAIL"
        fi
        # Extract duration
        local duration=""
        local dur_line
        dur_line=$(grep -m1 '\*\*Duration:\*\*' "$f" 2>/dev/null || true)
        if [[ -n "$dur_line" ]]; then
            duration=$(echo "$dur_line" | sed 's/.*\*\*Duration:\*\* *//' | sed 's/ .*//')
        fi
        entries+=("${mtime}|done|${status}|${duration}|${name}|${f}")
    done

    if [[ ${#entries[@]} -eq 0 ]]; then
        SIDEBAR_LINES+=("$(printf " ${NC}%-${inner_w}s${NC}" "(no history)")")
        SIDEBAR_LINES+=("")
        SIDEBAR_LINES+=("$(printf " ${NC}%-${inner_w}s${NC}" "○ idle")")
        return
    fi

    # Sort by mtime descending, take top entries that fit
    local sorted
    sorted=$(printf '%s\n' "${entries[@]}" | sort -t'|' -k1 -rn | head -15)

    local now; now=$(date +%s)
    local count=0
    while IFS='|' read -r mtime etype estatus einfo ename epath; do
        [[ -z "$mtime" ]] && continue

        # Age
        local age=$(( now - mtime ))
        local age_str
        if (( age < 3600 )); then
            age_str="$((age / 60))m"
        elif (( age < 86400 )); then
            age_str="$((age / 3600))h"
        else
            age_str="$((age / 86400))d"
        fi

        # Status icon
        local icon color
        case "$estatus" in
            PASS) icon="✓"; color="$GREEN" ;;
            FAIL) icon="✗"; color="$RED" ;;
            *)    icon="·"; color="$NC" ;;
        esac

        # Truncate name for display
        local display_name="$ename"
        local max_name=$((inner_w - 8))  # icon + age + spaces
        if (( ${#display_name} > max_name )); then
            display_name="${display_name:0:$((max_name - 2))}.."
        fi

        SIDEBAR_LINES+=("$(printf " %b%s%b %-${max_name}s %b%3s%b" "$color" "$icon" "$NC" "$display_name" "$NC" "$age_str" "$NC")")
        count=$((count + 1))
    done <<< "$sorted"

    # Footer
    SIDEBAR_LINES+=("$(printf " ${CYAN}%s${NC}" "$(printf '%*s' "$inner_w" '' | tr ' ' '─')")")
    SIDEBAR_LINES+=("$(printf " ${NC}%-${inner_w}s${NC}" "○ idle — $count runs")")
}

_tui_build_sidebar_status() {
    local inner_w="$1"
    local has_tmux=false
    local has_process=false

    if command -v tmux &>/dev/null && tmux has-session -t ultraworks 2>/dev/null; then has_tmux=true; fi
    if pgrep -f "opencode run.*auto" &>/dev/null; then has_process=true; fi

    if [[ "$has_tmux" == true && "$has_process" == true ]]; then
        SIDEBAR_LINES+=("$(printf " ${GREEN}● live${NC}%*s" $((inner_w - 6)) "")")
    elif [[ "$has_tmux" == true ]]; then
        SIDEBAR_LINES+=("$(printf " ${RED}✗ dead${NC}%*s" $((inner_w - 6)) "")")
    elif [[ "$has_process" == true ]]; then
        SIDEBAR_LINES+=("$(printf " ${YELLOW}? detached${NC}%*s" $((inner_w - 10)) "")")
    else
        SIDEBAR_LINES+=("$(printf " ${NC}○ idle${NC}%*s" $((inner_w - 6)) "")")
    fi
}

# Build left-panel content into LEFT_LINES array
_tui_build_left() {
    LEFT_LINES=()
    local left_w="$1"

    # Live status line
    local has_tmux=false has_process=false opencode_pid=""
    if command -v tmux &>/dev/null && tmux has-session -t ultraworks 2>/dev/null; then has_tmux=true; fi
    opencode_pid=$(pgrep -f "opencode run.*auto" 2>/dev/null | head -1 || true)
    [[ -n "$opencode_pid" ]] && has_process=true

    local now; now=$(date +%s)

    # Status header
    if [[ "$has_tmux" == true && "$has_process" == true ]]; then
        local log_info=""
        local latest_log
        latest_log=$(ls -t "$PIPELINE_DIR/logs"/task-*.log 2>/dev/null | head -1 || true)
        if [[ -n "$latest_log" ]]; then
            local log_size log_mtime log_idle
            log_size=$(wc -c < "$latest_log" 2>/dev/null | tr -d ' ')
            log_mtime=$(stat -c %Y "$latest_log" 2>/dev/null || echo "$now")
            log_idle=$(( now - log_mtime ))
            local log_kb=$(( log_size / 1024 ))
            if (( log_idle > 300 )); then
                log_info="  ${RED}no activity ${log_idle}s${NC}"
            elif (( log_idle > 60 )); then
                log_info="  ${YELLOW}idle ${log_idle}s${NC}"
            fi
            LEFT_LINES+=("$(printf "  ${GREEN}● RUNNING${NC}  pid=%s  log=%sKB%b" "$opencode_pid" "$log_kb" "$log_info")")
        else
            LEFT_LINES+=("$(printf "  ${GREEN}● RUNNING${NC}  pid=%s" "$opencode_pid")")
        fi
    elif [[ "$has_tmux" == true ]]; then
        LEFT_LINES+=("$(printf "  ${RED}✗ DEAD${NC}  tmux exists, opencode not found")")
    elif [[ "$has_process" == true ]]; then
        LEFT_LINES+=("$(printf "  ${YELLOW}? DETACHED${NC}  pid=%s, no tmux" "$opencode_pid")")
    else
        LEFT_LINES+=("$(printf "  ${NC}○ IDLE${NC}  no active session")")
    fi
    LEFT_LINES+=("")

    # Phase info
    local phase; phase=$(get_current_phase)
    LEFT_LINES+=("$(printf "  Phase: ${CYAN}%s${NC}" "$phase")")

    # Plan profile
    if [[ -f "$PIPELINE_DIR/plan.json" ]]; then
        local profile; profile=$(jq -r '.profile // "?"' "$PIPELINE_DIR/plan.json" 2>/dev/null)
        LEFT_LINES+=("$(printf "  Profile: %s" "$profile")")
    fi
    LEFT_LINES+=("")

    # Handoff content (main body)
    local handoff
    handoff=$(_tui_find_handoff)
    if [[ -n "$handoff" ]]; then
        LEFT_LINES+=("$(printf "  ${GREEN}Handoff:${NC}")")
        LEFT_LINES+=("$(printf "  ${BLUE}%s${NC}" "$(printf '%*s' $((left_w - 4)) '' | tr ' ' '─')")")
        while IFS= read -r line; do
            # Truncate long lines
            if (( ${#line} > left_w - 4 )); then
                line="${line:0:$((left_w - 7))}..."
            fi
            LEFT_LINES+=("  $line")
        done < <(head -50 "$handoff")
    else
        LEFT_LINES+=("  (no handoff)")
    fi

    LEFT_LINES+=("")

    # Pending tasks
    local pending; pending=$(list_pending_tasks)
    if [[ -n "$pending" ]]; then
        LEFT_LINES+=("$(printf "  ${YELLOW}Pending tasks:${NC}")")
        while IFS= read -r task_file; do
            [[ -z "$task_file" ]] && continue
            local name; name=$(basename "$task_file" .md)
            LEFT_LINES+=("    $name")
        done <<< "$pending"
    fi
}

# Merge left and right panels line-by-line
_tui_render_frame() {
    _tui_term_size
    local sidebar_w=$((TUI_COLS / 3))
    (( sidebar_w < 20 )) && sidebar_w=20
    (( sidebar_w > 40 )) && sidebar_w=40
    local left_w=$((TUI_COLS - sidebar_w - 1))  # -1 for border

    _tui_build_left "$left_w"
    _tui_build_sidebar "$sidebar_w"

    # Available content rows (minus header 2 + footer 2)
    local avail=$((TUI_ROWS - 4))
    (( avail < 5 )) && avail=5

    # Clamp scroll offset
    local left_total=${#LEFT_LINES[@]}
    local max_scroll=$(( left_total - avail ))
    (( max_scroll < 0 )) && max_scroll=0
    (( TUI_SCROLL > max_scroll )) && TUI_SCROLL=$max_scroll
    (( TUI_SCROLL < 0 )) && TUI_SCROLL=0

    local scrollable=false
    (( left_total > avail )) && scrollable=true

    # Scroll indicator
    local scroll_hint=""
    if [[ "$scrollable" == true ]]; then
        if (( TUI_SCROLL > 0 && TUI_SCROLL < max_scroll )); then
            scroll_hint="  ↑↓ scroll"
        elif (( TUI_SCROLL == 0 )); then
            scroll_hint="  ↓ more"
        else
            scroll_hint="  ↑ back"
        fi
    fi

    # Header
    printf '\033[H'  # cursor home
    printf '\033[2K'
    printf "  ${CYAN}Ultraworks Monitor${NC}  %s  ${CYAN}q${NC}=quit%b\n" "$(date '+%H:%M:%S')" "$scroll_hint"
    printf '\033[2K'
    printf "${CYAN}%s${NC}\n" "$(printf '%*s' "$TUI_COLS" '' | tr ' ' '─')"

    local i
    for (( i = 0; i < avail; i++ )); do
        local left_idx=$(( i + TUI_SCROLL ))
        local left_line="${LEFT_LINES[$left_idx]:-}"
        local right_line="${SIDEBAR_LINES[$i]:-}"

        # Strip ANSI for width calculation
        local left_visible
        left_visible=$(printf '%b' "$left_line" | sed 's/\x1b\[[0-9;]*m//g')
        local left_len=${#left_visible}
        local pad=$((left_w - left_len))
        (( pad < 0 )) && pad=0

        printf '\033[2K'  # clear line
        printf '%b%*s│%b\n' "$left_line" "$pad" "" "$right_line"
    done

    # Footer
    printf '\033[2K'
    printf "${CYAN}%s${NC}\n" "$(printf '%*s' "$TUI_COLS" '' | tr ' ' '─')"
    printf '\033[2K'
    printf "  ${NC}[q] quit  [a] attach  [l] logs  [j/k] scroll  [g/G] top/end${NC}\n"
}

# Read a single key or escape sequence from the TUI input fd (non-blocking).
# Sets TUI_KEY to the key/sequence, or "" if nothing available.
_tui_read_key() {
    TUI_KEY=""
    [[ -z "${TUI_INPUT_FD:-}" ]] && return
    local ch
    ch=$(dd bs=1 count=1 2>/dev/null <&"$TUI_INPUT_FD" || true)
    [[ -z "$ch" ]] && return
    if [[ "$ch" == $'\033' ]]; then
        # Possible escape sequence — read up to 5 more bytes quickly
        local seq=""
        local i
        for i in 1 2 3 4 5; do
            local next
            next=$(dd bs=1 count=1 2>/dev/null <&"$TUI_INPUT_FD" || true)
            [[ -z "$next" ]] && break
            seq+="$next"
            # Standard CSI sequences end at a letter
            if [[ "$next" =~ [A-Za-z~] ]]; then break; fi
        done
        TUI_KEY="${ch}${seq}"
    else
        TUI_KEY="$ch"
    fi
}

# Drain all pending input, accumulate scroll delta and detect action keys.
# Sets: TUI_ACTION (key action to take) and TUI_SCROLL_DELTA (accumulated scroll)
_tui_drain_input() {
    TUI_ACTION=""
    TUI_SCROLL_DELTA=0
    local got_input=false

    while true; do
        _tui_read_key
        [[ -z "$TUI_KEY" ]] && break
        got_input=true

        case "$TUI_KEY" in
            q|Q)          TUI_ACTION="quit"; return ;;
            a|A)          TUI_ACTION="attach"; return ;;
            l|L)          TUI_ACTION="logs"; return ;;
            g)            TUI_ACTION="top"; return ;;
            G)            TUI_ACTION="bottom"; return ;;
            # Arrow up / k — accumulate scroll up
            $'\033[A'|k|K)
                TUI_SCROLL_DELTA=$((TUI_SCROLL_DELTA - 1))
                ;;
            # Arrow down / j — accumulate scroll down
            $'\033[B'|j|J)
                TUI_SCROLL_DELTA=$((TUI_SCROLL_DELTA + 1))
                ;;
            # Mouse wheel up (SGR encoding: ESC[<65;...M or legacy ESC[Ma...)
            $'\033[<65'*|$'\033[Ma'*)
                TUI_SCROLL_DELTA=$((TUI_SCROLL_DELTA - 1))
                ;;
            # Mouse wheel down
            $'\033[<64'*|$'\033[M`'*)
                TUI_SCROLL_DELTA=$((TUI_SCROLL_DELTA + 1))
                ;;
        esac
    done

    [[ "$got_input" == true ]] && TUI_ACTION="scroll"
}

_tui_watch() {
    local refresh="${1:-3}"
    TUI_SCROLL=0  # Left panel scroll offset
    local last_render=0
    TUI_INPUT_FD=""

    # Open a dedicated fd for keyboard input.
    # When launched via make or pipe, stdin is not a tty.
    # /dev/tty gives us the real terminal regardless of stdin redirection.
    # Test if /dev/tty is actually usable (not just exists as device node)
    if (echo -n < /dev/tty) 2>/dev/null; then
        exec 9</dev/tty
        TUI_INPUT_FD=9
        stty -echo -icanon min 0 time 0 </dev/tty 2>/dev/null || true
    elif [[ -t 0 ]]; then
        exec 9<&0
        TUI_INPUT_FD=9
        stty -echo -icanon min 0 time 0 2>/dev/null || true
    fi
    # If neither — TUI_INPUT_FD stays empty, keys won't work but display will

    # Enter alternate screen, hide cursor
    printf '\033[?1049h'
    tput civis 2>/dev/null || true

    # Restore on exit
    trap '_tui_cleanup' EXIT INT TERM

    while true; do
        _tui_render_frame
        last_render=$(date +%s)

        # Wait for input with timeout, using debounce
        local deadline=$((last_render + refresh))
        TUI_ACTION=""

        while true; do
            local now
            now=$(date +%s)
            (( now >= deadline )) && break

            # Drain all pending input at once (debounce)
            _tui_drain_input

            if [[ -n "$TUI_ACTION" ]]; then
                break
            fi
            # Small sleep to avoid busy-loop, but short enough for responsiveness
            sleep 0.15
        done

        # Apply accumulated scroll delta (clamped)
        if [[ "$TUI_SCROLL_DELTA" -ne 0 ]]; then
            TUI_SCROLL=$((TUI_SCROLL + TUI_SCROLL_DELTA * 2))
            if (( TUI_SCROLL < 0 )); then TUI_SCROLL=0; fi
            local max_scroll=$(( ${#LEFT_LINES[@]} - (TUI_ROWS - 5) ))
            (( max_scroll < 0 )) && max_scroll=0
            (( TUI_SCROLL > max_scroll )) && TUI_SCROLL=$max_scroll
        fi

        case "$TUI_ACTION" in
            quit)
                return 0
                ;;
            top)
                TUI_SCROLL=0
                ;;
            bottom)
                local max_scroll=$(( ${#LEFT_LINES[@]} - (TUI_ROWS - 5) ))
                (( max_scroll < 0 )) && max_scroll=0
                TUI_SCROLL=$max_scroll
                ;;
            attach)
                _tui_cleanup_soft
                tmux attach -t ultraworks 2>/dev/null || echo "No ultraworks session"
                _tui_reenter
                ;;
            logs)
                _tui_cleanup_soft
                local log_dir="$PIPELINE_DIR/logs"
                local latest
                latest=$(ls -t "$log_dir"/task-*.log 2>/dev/null | head -1 || true)
                if [[ -n "$latest" ]]; then
                    less "$latest"
                else
                    echo "No logs" && sleep 1
                fi
                _tui_reenter
                ;;
        esac
    done
}

_tui_reenter() {
    if (echo -n < /dev/tty) 2>/dev/null; then
        exec 9</dev/tty
        TUI_INPUT_FD=9
        stty -echo -icanon min 0 time 0 </dev/tty 2>/dev/null || true
    elif [[ -t 0 ]]; then
        exec 9<&0
        TUI_INPUT_FD=9
        stty -echo -icanon min 0 time 0 2>/dev/null || true
    fi
    printf '\033[?1049h'
    tput civis 2>/dev/null || true
}

_tui_cleanup_soft() {
    printf '\033[?1049l'
    tput cnorm 2>/dev/null || true
    # Restore terminal settings
    stty echo icanon </dev/tty 2>/dev/null || stty echo icanon 2>/dev/null || true
    # Close input fd
    exec 9<&- 2>/dev/null || true
    TUI_INPUT_FD=""
}

_tui_cleanup() {
    _tui_cleanup_soft
    trap - EXIT INT TERM
    exit 0
}

# Main
main() {
    local action="${1:-show}"
    local task="${2:-}"
    
    case "$action" in
        show|state)
            show_state
            ;;
        launch|run)
            launch_opencode_tmux "$task"
            ;;
        headless)
            # Direct execution without tmux — outputs to stdout + log file
            # Useful when called from Claude Code or CI
            if [[ -z "$task" ]]; then
                echo -e "${RED}Error: task description required${NC}"
                echo "Usage: $0 headless \"task description\""
                exit 1
            fi
            local model
            model=$(_detect_model)
            if [[ -n "$model" ]]; then
                echo -e "${BLUE}Model:${NC} $model"
            fi
            local log_file
            log_file=$(_task_log_path "$task")
            local start_epoch
            start_epoch=$(date +%s)
            echo -e "${GREEN}Running Sisyphus pipeline (headless)...${NC}"
            echo -e "${BLUE}Task:${NC} $task"
            echo -e "${BLUE}Log:${NC} $log_file"
            if command -v timeout &>/dev/null && [[ "$ULTRAWORKS_MAX_RUNTIME" =~ ^[0-9]+$ ]] && (( ULTRAWORKS_MAX_RUNTIME > 0 )); then
                echo -e "${BLUE}Max runtime:${NC} ${ULTRAWORKS_MAX_RUNTIME}s"
            fi
            if [[ "$ULTRAWORKS_STALL_TIMEOUT" =~ ^[0-9]+$ ]] && (( ULTRAWORKS_STALL_TIMEOUT > 0 )); then
                echo -e "${BLUE}Stall watchdog:${NC} ${ULTRAWORKS_STALL_TIMEOUT}s"
            fi
            echo ""
            _run_headless_pipeline "$task" "$model" "$log_file" "$start_epoch"
            exit $?
            ;;
        logs)
            # Show recent task logs
            local log_dir="$PIPELINE_DIR/logs"
            if [[ -n "$task" ]]; then
                # View specific log
                if [[ -f "$task" ]]; then
                    less "$task"
                elif [[ -f "$log_dir/$task" ]]; then
                    less "$log_dir/$task"
                else
                    # Search by pattern
                    local found
                    found=$(ls -t "$log_dir"/task-*"$task"* 2>/dev/null | head -1)
                    if [[ -n "$found" ]]; then
                        less "$found"
                    else
                        echo -e "${RED}No log matching '$task'${NC}"
                        echo "Available logs:"
                        ls -lt "$log_dir"/task-*.log 2>/dev/null | head -10 | awk '{print "  " $NF}'
                    fi
                fi
            else
                # List recent logs
                echo -e "${CYAN}Recent task logs:${NC}"
                ls -lt "$log_dir"/task-*.log 2>/dev/null | head -15 | while read -r line; do
                    local f=$(echo "$line" | awk '{print $NF}')
                    local sz=$(echo "$line" | awk '{print $5}')
                    local dt=$(echo "$line" | awk '{print $6, $7, $8}')
                    local name=$(basename "$f")
                    # Check if log ends with "Pipeline finished" (success) or has error
                    local status="?"
                    if tail -5 "$f" 2>/dev/null | grep -q "Pipeline finished"; then
                        status="done"
                    elif tail -20 "$f" 2>/dev/null | grep -qi "error\|failed\|exception"; then
                        status="FAIL"
                    elif [[ $sz -lt 100 ]]; then
                        status="empty"
                    fi
                    printf "  %-8s %6s  %s  %s\n" "[$status]" "$(numfmt --to=iec $sz 2>/dev/null || echo ${sz}B)" "$dt" "$name"
                done || echo "  (no task logs)"
                echo ""
                echo -e "View a log: ${CYAN}$0 logs <filename-or-pattern>${NC}"
            fi
            ;;
        watch)
            # Live TUI with split panels — agent sidebar on the right
            _tui_watch "${task:-3}"
            ;;
        attach)
            tmux attach -t ultraworks 2>/dev/null || echo -e "${YELLOW}No ultraworks session. Run: $0 launch \"task\"${NC}"
            ;;
        menu|interactive)
            interactive_menu
            ;;
        *)
            show_state
            echo ""
            echo -e "${CYAN}Usage: $0 [show|launch|headless|watch|logs|attach|menu] [task description]${NC}"
            echo ""
            echo "Commands:"
            echo "  show      Show current pipeline state (default)"
            echo "  watch     Live TUI monitor with agent sidebar (split-panel)"
            echo "  launch    Start Sisyphus pipeline in tmux session (logs to file)"
            echo "  headless  Run pipeline directly (stdout + log file)"
            echo "  logs      List recent task logs, or view one: logs <pattern>"
            echo "  attach    Attach to existing tmux session"
            echo "  menu      Interactive menu"
            echo ""
            echo "Examples:"
            echo "  $0 watch                   # live split-panel monitor"
            echo "  $0 launch \"Implement user authentication\""
            echo "  $0 headless \"Add metrics dashboard\""
            echo "  $0 logs                    # list recent logs"
            echo "  $0 logs e2e                # view latest log matching 'e2e'"
            echo "  $0 attach"
            ;;
    esac
}

main "$@"
