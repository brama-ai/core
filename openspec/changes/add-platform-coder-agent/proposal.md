# Change: Add Built-in Platform Coder Agent

## Why

The repository already has a working builder workflow in `builder/` with task folders, a multi-agent pipeline, and a terminal monitor. That workflow is useful but operator-hostile: it requires shell access, manual task file creation, and terminal-based monitoring.

The platform needs a first-class Core-admin interface for this workflow so admins can create tasks, inspect progress, manage queue state, and review logs without leaving the web UI. The first release must keep the existing builder runtime operational instead of replacing it.

## What Changes

- **New capability: `coder-agent-admin`** -- admin pages under `/admin/coder` for task creation, task list, detail view, worker visibility, and live monitoring
- **New capability: `coder-agent-worker`** -- Symfony worker commands and task lifecycle tracking in Core
- **New capability: `coder-agent-pipeline`** -- Core wrapper around the existing `builder/pipeline.sh` runtime with subprocess log capture and stage detection
- **New capability: `coder-agent-worktree`** -- worktree tracking and artifact visibility compatible with the existing builder layout
- **Modified: admin navigation** -- new `Coder` entry in the Core sidebar
- **Modified architecture** -- Core DB becomes the primary UI state store while `builder/tasks/*` and `.opencode/pipeline/*` remain the compatibility/runtime layer in phase 1
- **Deferred** -- A2A exposure is designed to align with the data model but is not required for the first web-admin delivery

## Impact

- Affected specs: `coder-agent-admin`, `coder-agent-pipeline`, `coder-agent-worker`, `coder-agent-worktree`, `admin-tools-navigation`
- Affected code: new `apps/core/src/CoderAgent/` namespace, migrations, repositories, worker commands, admin controllers, internal APIs, Twig templates, and sidebar updates
- Runtime impact: existing `builder/pipeline.sh`, `builder/pipeline-batch.sh`, and `builder/monitor/pipeline-monitor.sh` remain supported
- No breaking change to the current builder CLI workflow
