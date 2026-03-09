# Change: Add Built-in Platform Coder Agent

## Why

The platform already has a proven multi-agent coding pipeline (`scripts/pipeline.sh`, `pipeline-batch.sh`, `pipeline-monitor.sh`) that runs architect, coder, validator, tester, and documenter stages. However, this pipeline is entirely CLI-based -- operators must SSH into the machine, manage task files manually, and monitor progress through a terminal TUI. There is no way to create, prioritize, or monitor coding tasks from the admin panel, and no integration with the platform's A2A protocol or existing worker infrastructure.

A built-in Platform Coder Agent will wrap the existing pipeline logic in a PHP/Symfony service, expose task management through the admin UI, support concurrent workers via git worktrees, and integrate with the A2A protocol so other agents can trigger coding tasks programmatically.

## What Changes

- **New capability: `coder-agent-pipeline`** -- PHP service that orchestrates the 5-stage pipeline (architect, coder, validator, tester, documenter) with stage gate verification, retry policies, and model fallback chains via LiteLLM gateway
- **New capability: `coder-agent-admin`** -- Admin panel pages for task CRUD, priority management, pipeline monitoring dashboard with real-time progress (SSE), log viewing, and task templates (ADR, HLD, feature spec, bug fix, refactor)
- **New capability: `coder-agent-worker`** -- Background worker management: spawn, monitor, and kill workers; priority queue with configurable concurrency; task state machine (todo/queued/in-progress/done/failed)
- **New capability: `coder-agent-worktree`** -- Git worktree lifecycle management for worker isolation: create, track, and cleanup worktrees per task
- **Modified: A2A integration** -- New A2A skills (`coder.submit_task`, `coder.task_status`, `coder.cancel_task`) so other platform agents can request coding work
- **Modified: admin navigation** -- New "Coder" section in admin sidebar

## Impact

- Affected specs: coder-agent-pipeline (new), coder-agent-admin (new), coder-agent-worker (new), coder-agent-worktree (new), admin-tools-navigation (modified)
- Affected code: new `apps/core/src/CoderAgent/` namespace, new admin controllers and templates, new Symfony commands for workers, new database tables, modifications to admin navigation
- **New external dependencies**: none beyond existing stack (PHP 8.5, Symfony 7, Postgres, Redis for queue); existing LiteLLM gateway for model selection
- **No breaking changes** to existing agents or platform APIs
