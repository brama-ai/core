# pipeline-monitor

## ADDED Requirements

### Requirement: Permanent Logs Tab

The pipeline monitor MUST always display a Logs tab (tab 2) that shows the most recent log file from `.opencode/pipeline/logs/`, regardless of whether worktrees or parallel workers exist.

#### Scenario: Sequential pipeline run with no worktrees

- Given a pipeline run completed and log files exist in `.opencode/pipeline/logs/`
- When the user opens the monitor and presses Right arrow
- Then tab 2 "Logs" is selected and displays the tail of the most recent log file

#### Scenario: No log files exist

- Given no log files exist in `.opencode/pipeline/logs/`
- When the user navigates to the Logs tab
- Then the tab displays "No log files found" with the log directory path

### Requirement: Active Worker Count Display

The pipeline monitor Overview tab MUST display the number of active parallel workers in the status line.

#### Scenario: Batch running with multiple workers

- Given a batch process is running with 2 worktree directories
- When the user views the Overview tab
- Then the status shows "Running (Xm Ys elapsed, PID NNNNN, 2 workers)"

#### Scenario: Sequential batch running

- Given a batch process is running without worktrees
- When the user views the Overview tab
- Then the status shows "Running (Xm Ys elapsed, PID NNNNN, 1 worker)"

### Requirement: Smart Batch Start Prompt

The pipeline monitor MUST prompt the user to start a batch when tasks are waiting and no batch is running.

#### Scenario: Tasks waiting with no batch

- Given 3 task files exist in `tasks/todo/` and no `pipeline-batch.sh` process is running
- When the user views the Overview tab
- Then the status shows "Not running — 3 tasks waiting, press [s] to start"

#### Scenario: No tasks waiting

- Given no task files in `tasks/todo/` and no batch running
- When the user views the Overview tab
- Then the status shows "Not running" without a start prompt

### Requirement: Reliable Arrow Key Input on macOS bash 3.2

The pipeline monitor MUST correctly handle arrow key escape sequences on macOS bash 3.2 (GNU bash 3.2.57) without echoing raw escape characters to the terminal.

#### Scenario: Left and Right arrow keys switch tabs

- Given the monitor is running on macOS with bash 3.2
- And the user is on the Overview tab
- When the user presses the Right arrow key
- Then the monitor switches to the Logs tab without displaying `^[[C`

#### Scenario: Up and Down arrow keys navigate task list

- Given the monitor is on the Overview tab with 3 tasks displayed
- When the user presses Down arrow twice
- Then the cursor moves to the third task without displaying `^[[B`

### Requirement: Task Log Viewer

The pipeline monitor MUST allow viewing logs for a selected failed or in-progress task directly from the Overview tab.

#### Scenario: View logs for a failed task

- Given the user is on the Overview tab with cursor on a failed task
- And a log file matching the task slug exists in `.opencode/pipeline/logs/`
- When the user presses `l`
- Then the overview area shows the colorized tail of that task's log file
- And the bottom bar shows "q/Esc back"

#### Scenario: View logs for an in-progress task

- Given the user is on the Overview tab with cursor on an in-progress task
- When the user presses `l`
- Then the overview area shows the latest matching log file for that task

#### Scenario: No matching log file

- Given the user is on the Overview tab with cursor on a failed task
- And no log file matches the task slug
- When the user presses `l`
- Then the log view shows "No log file found for this task"

#### Scenario: Return from log view

- Given the user is viewing a task's logs
- When the user presses `q` or `Esc`
- Then the monitor returns to the task list view

#### Scenario: Press l on a non-eligible task

- Given the user is on the Overview tab with cursor on a todo or done task
- When the user presses `l`
- Then the action bar shows "Logs available for failed/in-progress tasks only"

### Requirement: Full-Height Log Display

The Logs tab, Worker tabs, and Task Log Viewer MUST maximize the terminal height for log content by using a compact layout with minimal chrome.

#### Scenario: Logs tab uses full terminal height

- Given the terminal has 40 rows
- When the user views the Logs tab
- Then the tab bar, file info header, and footer occupy at most 4 lines
- And log content fills the remaining 36 lines

#### Scenario: Worker tab uses full terminal height

- Given the terminal has 40 rows
- When the user views a Worker tab
- Then the layout matches the Logs tab: at most 4 lines of chrome, rest is log content

#### Scenario: Task log viewer uses full height

- Given the user presses `l` on a failed task
- Then the log viewer header and overview chrome occupy at most 7 lines
- And the remaining terminal height shows log content

### Requirement: Render Performance Caching

The pipeline monitor MUST cache expensive filesystem and process lookups between render cycles to reduce subprocess spawning from ~50-70 per render to ~5-10.

#### Scenario: Cached render cycle

- Given the monitor has completed an initial render
- When the next render cycle fires within the cache TTL (2 cycles)
- Then worker detection, task list building, log scanning, and task counts use cached values
- And no new `find`, `pgrep`, `ls` subprocesses are spawned for cached data

#### Scenario: Cache invalidation on user action

- Given the user presses `s` (start), `k` (kill), `a` (archive), or `d` (delete)
- Then the cache is immediately invalidated
- And the next render cycle rebuilds all data from the filesystem

#### Scenario: Bash built-ins replace external commands

- Given `count_files()` is called for task directories
- Then it uses bash glob expansion (`"$dir"/*.md`) instead of `find | wc -l`
- And title extraction uses `IFS= read -r` instead of `grep | sed`

### Requirement: Automatic Log Cleanup

The pipeline monitor MUST clean up old log files on startup to prevent unbounded disk usage.

#### Scenario: Startup cleanup with default retention

- Given `LOG_RETENTION_DAYS=7` (default)
- When the monitor starts
- Then all `.log` and `.meta.json` files in `$LOG_DIR` older than 7 days are deleted
- And all `batch_*.md` report files in `$REPORT_DIR` older than 7 days are deleted
- And cleanup runs only once per monitor session

#### Scenario: Cleanup disabled

- Given `LOG_RETENTION_DAYS=0`
- When the monitor starts
- Then no log files are deleted

### Requirement: Worker Liveness Detection

The pipeline monitor MUST only show worker tabs for workers that are actually alive, removing phantom tabs for dead workers.

#### Scenario: Worker process detected via pgrep

- Given `pgrep -f "pipeline.sh.*worker-1"` returns a match
- Then worker-1 is considered alive and its tab is shown

#### Scenario: Worker detected via worktree mtime fallback

- Given no matching pgrep result for worker-1
- But the worktree directory `$WORKTREE_BASE/worker-1` was modified within the last 60 seconds
- And a batch process is running
- Then worker-1 is considered alive (it may be between stages)

#### Scenario: Dead worker removed

- Given no pgrep match and no recent mtime for worker-2
- Then worker-2's tab is removed from the tab bar
- And `ACTIVE_WORKER_COUNT` decreases accordingly

### Requirement: Token Aggregation and Cost Estimation

The pipeline monitor MUST display aggregated token usage and approximate cost for the current batch session.

#### Scenario: Token stats in cost bar

- Given `.meta.json` files exist in `$LOG_DIR` and worktree log directories
- When the monitor renders any tab
- Then the bottom bar shows: `Tokens: ↓{input}K ↑{output}K cache:{read}r/{write}w ≈${cost}`
- And cost is estimated using Claude Sonnet pricing ($3/$15 per 1M input/output tokens)

#### Scenario: No meta files

- Given no `.meta.json` files exist
- When the monitor renders
- Then the token stats section shows zeros

### Requirement: Provider Balance Display

The pipeline monitor MUST display the OpenRouter API balance and usage percentage in the cost bar.

#### Scenario: OpenRouter balance query

- Given `OPENROUTER_API_KEY` is set in the environment
- When the monitor renders (with a TTL of 30 render cycles between API calls)
- Then it queries `GET https://openrouter.ai/api/v1/auth/key` with the API key
- And displays: `OpenRouter: {usage_pct}% used (${usage}/${limit})`

#### Scenario: Balance color coding

- Given usage is below 50%: display in green
- Given usage is 50-80%: display in yellow
- Given usage is above 80%: display in red

#### Scenario: No API key

- Given `OPENROUTER_API_KEY` is not set
- Then the OpenRouter balance section is not displayed

### Requirement: Cost Breakdown in Summary File

The pipeline (`scripts/pipeline.sh`) MUST append a cost breakdown table to the summary file generated by the summarizer agent.

#### Scenario: Summary file with cost table

- Given a pipeline run completed and the summarizer agent generated `$TASK_SUMMARY_FILE`
- When the pipeline finishes
- Then it appends a "Вартість пайплайну" section with a per-agent cost table
- And columns include: Agent, Duration, Input tokens, Output tokens, Cache Read, Cache Write, Estimated Cost
- And a total row summarizes all agents

### Requirement: macOS bash 3.2 Array Safety

The pipeline monitor MUST handle empty array expansion safely under `set -u` on macOS bash 3.2, which treats `"${empty_array[@]}"` as an unbound variable error.

#### Scenario: Empty DETECTED_WORKERS

- Given no workers are detected
- When `update_worker_state()` or `render_tabs_str()` iterates over workers
- Then it uses index-based loops (`for ((i=0; i<COUNT; i++))`) instead of `"${array[@]}"`

#### Scenario: Empty CACHED_LOG_MAP

- Given no log mappings are cached
- When rendering in-progress tasks checks for log entries
- Then it uses the safe expansion pattern `${arr[@]+"${arr[@]}"}`

#### Scenario: Empty meta_files in build_activity_events

- Given no `.meta.json` files are found
- When `build_activity_events()` is called
- Then it returns early before iterating, avoiding unbound variable errors

## ADDED Requirements

### Requirement: Tab Navigation Layout

The pipeline monitor tab bar MUST use a fixed layout: tab 1 = Overview, tab 2 = Logs, tabs 3+ = dynamic worker tabs.

#### Scenario: No active workers

- Given no parallel workers are running
- Then the tab bar shows "1:Overview  2:Logs"
- And Right from Overview goes to Logs, Right from Logs does nothing

#### Scenario: Two active workers

- Given 2 worker worktrees exist
- Then the tab bar shows "1:Overview  2:Logs  3:worker-1  4:worker-2"
- And number keys 1-4 jump directly to the corresponding tab
