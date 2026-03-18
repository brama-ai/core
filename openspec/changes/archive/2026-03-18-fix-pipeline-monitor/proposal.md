# Proposal: Rewrite Pipeline Monitor

## Why

The bash-based pipeline monitor (`scripts/pipeline-monitor.sh`) has accumulated too many patches and is unreliable:

1. **Arrow keys don't work**: bash 3.2 on macOS handles escape sequences poorly. Multiple attempts to fix `read_key()` with `stty` raw mode, byte-by-byte reads, and various `min`/`time` settings have all failed. The fundamental problem is bash 3.2's `read` builtin fighting with terminal raw mode.
2. **Logs tab shows nothing**: Log discovery only checks worktree directories. Sequential runs write to `$LOG_DIR` but no tab renders those.
3. **No worker count**: Overview shows PID but not how many parallel workers are active.
4. **No smart start**: When tasks wait in `todo/` and no batch runs, the monitor passively says "Not running" instead of prompting to start.

Patching further will not fix the core issue — bash 3.2 is not suited for interactive TUI with escape sequences. The solution is a clean rewrite.

## What Changes

### Replaced Capability: `pipeline-monitor`

**Delete** `scripts/pipeline-monitor.sh` and **rewrite** it from scratch with:

1. **Proper terminal handling**: Use `stty -echo -icanon` with per-byte `read` and explicit `dd` fallback for escape sequence reading on bash 3.2.
2. **Fixed tab layout**: Always show `1:Overview  2:Logs  [3:worker-1 ...]`. Arrow keys always work because Logs tab is always present.
3. **Log viewer**: Tab 2 shows the latest `.log` file from `.opencode/pipeline/logs/` with colorized tail output. Auto-refreshes every 3s.
4. **Worker count in status**: `"Running (3m 42s, PID 12345, 2 workers)"`.
5. **Smart start prompt**: `"Not running — 3 tasks waiting, press [s] to start"` when tasks in todo/.
6. **Task log viewer**: Press `[l]` on a failed or in-progress task to see its pipeline logs inline. `[q]` or `Esc` returns to the task list.
7. **Full-height log display**: Logs, Worker, and Task Log views use a compact layout (tab bar + 1 info line + footer = 4 lines chrome) so log content fills the remaining terminal height.
8. **Clean architecture**: Each section (render, input, actions) is self-contained. No global state leaking between render cycles.

### Data Sources

The monitor reads from the filesystem only — no live process communication:

| Data | Source |
|------|--------|
| Task list | `tasks/{todo,in-progress,done,failed}/*.md` |
| Batch status | `pgrep -f pipeline-batch.sh` |
| Worker count | Count dirs in `$WORKTREE_BASE/worker-*` |
| Agent stage | Latest `*.log` filename in logs dir → extract agent name |
| Log viewer | `tail -n N` of latest `.opencode/pipeline/logs/*.log` |
| Duration/branch | `<!-- batch: ... -->` comment in task `.md` files |
| Worker active task | `pgrep`/`ps` for pipeline.sh args in worktree |

### Keyboard Shortcuts (unchanged)

| Key | Action |
|-----|--------|
| `←/→` | Switch tabs |
| `↑/↓` | Select task in list |
| `Enter` | View task detail |
| `Esc` | Back from detail |
| `s` | Start batch (or extra worker for selected task) |
| `f` | Retry failed tasks |
| `k` | Kill batch |
| `x` | Stop in-progress task |
| `+/-` | Change priority of todo task |
| `l` | View logs for selected failed/in-progress task |
| `d` | Delete todo/failed task |
| `a` | Archive completed tasks |
| `q` | Quit (or back from log/detail view) |

### Post-Rewrite Enhancements (v0.12.0–v0.13.0)

After the initial rewrite, additional capabilities were added:

1. **Render performance caching**: Cache worker detection, task list, log scanning, and task counts with a 2-cycle TTL. Bash built-ins replace external commands (`find`, `grep`, `sed`). Reduces subprocess count from ~50-70 to ~5-10 per render cycle.
2. **Automatic log cleanup**: Old `.log`, `.meta.json`, and report files are cleaned up on startup (default retention: 7 days).
3. **Worker liveness detection**: `_worker_is_alive()` uses pgrep + worktree mtime fallback. Dead worker tabs are automatically removed.
4. **Token aggregation and cost estimation**: Aggregates token usage from `.meta.json` sidecar files. Estimates cost at Claude Sonnet rates. Displayed in a cost bar at the bottom of every tab.
5. **OpenRouter provider balance**: Queries OpenRouter API for usage/limit with color-coded percentage display.
6. **Cost breakdown in summary**: `scripts/pipeline.sh` appends a per-agent cost table to the summarizer's output file.
7. **macOS bash 3.2 array safety**: All empty array expansions are safe under `set -u` using index-based loops, safe expansion patterns, and early return guards.

## Impact

- **Replaced**: `scripts/pipeline-monitor.sh` — full rewrite, same filename
- **Modified**: `scripts/pipeline.sh` — cost breakdown appended to summary file
- **Backward compatible**: same CLI usage, same keyboard shortcuts, same task folder structure

## Tech Stack

- bash (must work on macOS bash 3.2 and Linux bash 5+)
- `stty` for terminal raw mode
- `tput` for colors/cursor
- `pgrep`/`ps` for process detection
- `jq` not required (already optional)
- Reads OpenCode artifacts: `.opencode/pipeline/logs/`, `.opencode/pipeline/reports/`
