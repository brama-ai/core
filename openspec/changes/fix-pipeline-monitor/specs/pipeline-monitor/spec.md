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

## MODIFIED Requirements

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
