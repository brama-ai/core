# pipeline-monitor Specification

## Purpose
TBD - created by archiving change fix-pipeline-monitor. Update Purpose after archive.
## Requirements
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

