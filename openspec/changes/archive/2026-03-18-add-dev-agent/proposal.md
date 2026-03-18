# Proposal: Add Dev Agent

## Why

The platform has a multi-agent development pipeline (`scripts/pipeline.sh`) that orchestrates 5 agents sequentially (architect → coder → validator → tester → documenter). Currently, this pipeline is only controllable via CLI. There is no way to:

1. Create and refine development tasks with LLM assistance before execution
2. Monitor pipeline progress in real-time through a web interface
3. Automatically create GitHub PRs after successful runs
4. Manage the full task lifecycle (create → refine → execute → review) from the admin panel

The dev-agent bridges this gap by providing a web-based development orchestration layer.

## What Changes

### New Capability: `dev-agent`

A new PHP agent (`apps/dev-agent/`) following the dev-reporter-agent scaffold:

- **A2A skills**:
  - `dev.create_task` — creates a development task with title, description, and pipeline options
  - `dev.run_pipeline` — queues a task for pipeline execution (async)
  - `dev.get_status` — returns task status, branch, PR URL, and log count
  - `dev.list_tasks` — lists recent tasks with optional status filter
- **Task creation with Opus**: multi-turn chat with Claude Opus 4.6 (via LiteLLM) to refine task specifications before pipeline execution
- **Live pipeline logs**: SSE (Server-Sent Events) endpoint streaming pipeline output in real-time — first SSE implementation in the platform
- **Auto PR creation**: after successful pipeline, pushes branch and creates GitHub PR via `gh` CLI
- **Admin panel**: task list, task creation with Opus chat, task detail with live logs

### New Spec: `dev-agent`

Defines the A2A contract, data model, SSE streaming contract, and LLM integration.

### New LiteLLM Model: `claude-opus-4-6`

Claude Opus 4.6 added to LiteLLM config via OpenRouter for task refinement.

### Infrastructure

- Traefik entrypoint `:8088` for dev-agent
- Postgres role `dev_agent` with dedicated database
- Docker compose `compose.agent-dev.yaml`
- Dockerfile includes `git` and `gh` CLI for PR creation

## Impact

- **New app**: `apps/dev-agent/` (PHP 8.5 + Symfony 7)
- **New compose file**: `compose.agent-dev.yaml`
- **New DB tables**: `dev_tasks`, `dev_task_logs` in agent-specific schema
- **Modified**: `docker/litellm/config.yaml` — adds Claude Opus 4.6
- **Modified**: `docker/traefik/traefik.yml` — adds entrypoint `:8088`
- **Modified**: `compose.yaml` — adds port 8088 to Traefik
- **Modified**: `docker/postgres/init/` — adds dev_agent role and databases
- **Modified**: `Makefile` — adds dev-agent targets
- **No breaking changes** to existing agents or Core
