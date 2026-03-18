# Tasks: Rewrite Pipeline Monitor

## Ordered Work Items

### 1. Implement terminal input handler with bash 3.2 compatibility
- Use `stty -echo -icanon` on entry, restore on exit
- Read escape sequences using `dd bs=1 count=1` as fallback when `read -rsn1` fails
- Test: arrow keys produce UP/DOWN/LEFT/RIGHT, no escape leakage
- Test: regular keys (s, q, f, k, etc.) work
- Test: Enter and Esc work
- **Verify**: run on macOS bash 3.2, confirm no `^[[D` garbage output

### 2. Implement flicker-free buffer renderer
- `buf_reset`, `buf_line`, `buf_flush` using cursor home + line-by-line overwrite
- Alternate screen buffer (`\033[?1049h/l`) for clean entry/exit
- Hide cursor during render, show on exit
- **Verify**: no flicker during 3s refresh cycle

### 3. Implement Overview tab (tab 1)
- Task counters: todo, in-progress, done, failed
- Progress bar: `[████░░░░] 3/8`
- Batch status with worker count: `"Running (3m 42s, PID 12345, 2 workers)"`
- Smart start prompt: `"Not running — N tasks waiting, press [s] to start"` when todo > 0
- Task list with cursor selection (▶), grouped by state
- Priority display for todo tasks
- Duration and branch for completed/failed tasks
- **Verify**: all counts match files in tasks/ directories

### 4. Implement Logs tab (tab 2, always present)
- Find latest log: `ls -t .opencode/pipeline/logs/*.log | head -1`
- Show agent name, filename, size in header
- Colorized tail: errors=red, success=green, separators=cyan
- Auto-refresh every 3s
- **Verify**: shows logs from sequential runs (no worktrees needed)

### 5. Implement Worker tabs (tab 3+, dynamic)
- Detect workers via `$WORKTREE_BASE/worker-*` directories
- Each tab shows latest log from worker's worktree log dir
- Fallback to main log dir if worktree has no logs
- Show active task name from `pgrep`/`ps` process args
- **Verify**: tabs appear/disappear as workers start/stop

### 6. Implement tab bar and navigation
- Always show: `1:Overview  2:Logs`
- Dynamically add: `3:worker-1  4:worker-2 ...`
- Left/Right arrows switch between tabs
- Number keys (1-9) jump to tab directly
- Active tab shown in reverse video
- **Verify**: LEFT from Overview does nothing, RIGHT goes to Logs, then workers

### 7. Implement task actions
- `s` — start batch (`caffeinate -s nohup pipeline-batch.sh ...`) or start extra worker for selected task
- `f` — retry failed (move failed→todo, delete branches, restart)
- `k` — kill batch (`pkill -f pipeline-batch.sh`)
- `x` — stop in-progress task (move to todo)
- `+/-` — change priority (read/write `<!-- priority: N -->` in task file)
- `d` — delete task (file + branch + logs + artifacts)
- `a` — archive completed (move done→archive)
- `Enter` — detail view, `Esc` — back
- **Verify**: each action matches current monitor behavior

### 8. Implement task detail view
- Show full task file content (minus priority/batch metadata)
- Scrollable within available terminal height
- Esc/Backspace returns to task list
- **Verify**: Enter on selected task shows detail, Esc returns

### 8b. Implement task log viewer (`[l]` key)
- `l` on a failed or in-progress task opens log view mode
- `find_task_log()` searches `$LOG_DIR` and worktree log dirs by task filename slug
- Falls back to title-based slug matching
- Renders colorized log tail in the overview area (reuses `render_log_lines`)
- `q` or `Esc` returns to task list (does not quit the monitor)
- Shows "No log file found" when no matching log exists
- Shows warning when pressed on todo/done tasks
- **Verify**: select failed task, press `l`, see logs; press `q`, return to list

### 9. Wire main loop
- `render` → `read_key` → `handle_key` → repeat
- 3s refresh interval when no key pressed
- Clean exit on q/Ctrl-C with stty restore
- **Verify**: monitor runs stable for 10+ minutes without memory/fd leaks

### 10. Update pipeline documentation
- Update `docs/features/pipeline/ua/pipeline.md` monitoring section with new tab layout
- Update `docs/features/pipeline/en/pipeline.md` same
- **Verify**: docs match actual behavior

### 11. Render performance caching (v0.12.0) ✅

- Cache worker detection, task list, log map, and task counts with 2-cycle TTL
- Replace `count_files()` with bash glob (no subshells)
- Replace `_extract_title()` with `IFS= read -r` (no grep/sed)
- Invalidate cache on user actions (start, kill, archive, delete)
- Reduces subprocess count from ~50-70 to ~5-10 per render cycle
- **Done**: Implemented and verified

### 12. Automatic log cleanup (v0.12.0) ✅

- `cleanup_old_logs()` runs once on startup
- Deletes `.log`, `.meta.json` files older than `LOG_RETENTION_DAYS` (default 7)
- Also cleans old `batch_*.md` reports
- Configurable: set `LOG_RETENTION_DAYS=0` to disable
- **Done**: Implemented and verified

### 13. Worker liveness detection (v0.12.0) ✅

- `_worker_is_alive()` checks pgrep + worktree mtime fallback
- Dead worker tabs automatically removed
- `ACTIVE_WORKER_COUNT` tracks only live workers
- **Done**: Implemented and verified

### 14. Token aggregation and cost estimation (v0.12.0) ✅

- `aggregate_batch_tokens()` collects all `.meta.json` from main + worktree log dirs
- Single-pass awk extracts input/output/cache_read/cache_write tokens
- `_estimate_cost()` calculates approximate cost at Claude Sonnet rates
- `render_cost_bar()` displays at bottom of every tab
- **Done**: Implemented and verified

### 15. OpenRouter provider balance display (v0.12.0) ✅

- `query_openrouter_balance()` queries `/api/v1/auth/key` with TTL caching
- Displays usage percentage with color coding (green/yellow/red)
- Shows `${usage}/${limit}` amounts
- Skipped when `OPENROUTER_API_KEY` is not set
- **Done**: Implemented and verified

### 16. Cost breakdown in summary file (v0.12.0) ✅

- `scripts/pipeline.sh` appends "Вартість пайплайну" table to `$TASK_SUMMARY_FILE`
- Per-agent rows: duration, input/output tokens, cache stats, estimated cost
- Grand total row at the bottom
- **Done**: Implemented and verified

### 17. macOS bash 3.2 empty array safety (v0.13.0) ✅

- Fixed `set -u` crashes with empty arrays on macOS bash 3.2
- `DETECTED_WORKERS=()` initialized at top level
- Index-based loops replace `"${array[@]}"` for potentially empty arrays
- Safe expansion `${arr[@]+"${arr[@]}"}` for `CACHED_LOG_MAP` and `meta_files`
- Early return guards in `build_activity_events()` and `aggregate_batch_tokens()`
- **Done**: Implemented and verified — monitor launches without crashes

## Dependencies

- Tasks 1-2 are the foundation (terminal + renderer)
- Tasks 3-6 depend on 1-2 (tabs and content)
- Task 7 depends on 3 (actions operate on task list)
- Task 8 depends on 3 (detail is a sub-view of overview)
- Task 9 wires everything together
- Task 10 is last
- Tasks 11-17 are independent enhancements applied after the initial rewrite
