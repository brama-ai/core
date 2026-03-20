## Context

The platform currently has no centralized scheduling. The news-maker-agent uses Python's APScheduler internally, but this approach doesn't scale to other agents and creates operational blind spots (no admin visibility, no retry policy, no persistence across restarts). The platform already has `A2AClient` for inter-agent communication and `AgentInstallerService` for lifecycle management — the scheduler builds on both.

## Goals / Non-Goals

- Goals:
  - Centralized, persistent job scheduling in core
  - Manifest-driven job registration (agents declare, platform executes)
  - Retry with dead-letter policy
  - Admin visibility and manual trigger capability
  - Graceful shutdown on SIGTERM/SIGINT
  - Idempotent execution (no duplicate concurrent runs)

- Non-Goals:
  - Replacing APScheduler in news-maker-agent (separate migration)
  - Sub-second scheduling precision
  - Distributed scheduler (single instance is sufficient for MVP)
  - Job dependency chains or DAGs
  - Cron expression editing via admin UI (jobs come from manifests)

## Decisions

### 1. Polling-based scheduler in a Symfony Command

- **Decision**: Long-running `scheduler:run` command with 10-second polling interval
- **Why**: Simple, debuggable, no additional infrastructure (no Redis queues, no RabbitMQ). Symfony commands already support signal handling via `SignalableCommandInterface`.
- **Alternatives considered**:
  - Symfony Scheduler component — requires Symfony Messenger, adds complexity; our jobs are A2A calls, not local handlers
  - Cron + short-lived command — loses state between runs, harder to implement retry/catch-up
  - RabbitMQ delayed messages — over-engineered for MVP scale

### 2. `FOR UPDATE SKIP LOCKED` for concurrency control

- **Decision**: Use `SELECT ... FOR UPDATE SKIP LOCKED` when picking due jobs
- **Why**: PostgreSQL-native, no external lock service needed. If a future second scheduler instance runs, jobs won't be double-picked. Simple and proven pattern.
- **Alternatives considered**:
  - Advisory locks — more complex, harder to debug
  - Application-level mutex — doesn't work across processes

### 3. Manifest-driven registration (not API-driven)

- **Decision**: Jobs are declared in agent `manifest.json` under `scheduled_jobs` and registered during the install lifecycle
- **Why**: Consistent with existing patterns (storage declarations in manifest). No need for agents to make API calls to register jobs. Single source of truth.
- **Alternatives considered**:
  - REST API for job registration — adds API surface, agents need auth tokens, more moving parts
  - Config file in core — doesn't scale, requires core changes per agent

### 4. `dragonmantank/cron-expression` for cron parsing

- **Decision**: Use this well-maintained PHP library (used by Laravel, Symfony Scheduler)
- **Why**: Standard crontab format, battle-tested, MIT license, PHP 8.1+ compatible
- **Alternatives considered**:
  - Custom parser — unnecessary complexity
  - Symfony CronExpression — doesn't exist as standalone; Symfony uses dragonmantank internally

### 5. Scheduler runs as a separate Docker service

- **Decision**: Add a `core-scheduler` service in `compose.core.yaml` that runs `php bin/console scheduler:run`
- **Why**: Decouples scheduler lifecycle from web server. Can be restarted independently. Standard pattern for worker processes.
- **Alternatives considered**:
  - Run inside web process — blocks request handling, no signal handling
  - Supervisord inside core container — adds complexity to container setup

## Risks / Trade-offs

- **Single point of failure**: One scheduler instance means if it crashes, jobs don't run until restart → Mitigation: Docker restart policy (`unless-stopped`), catch-up policy runs missed jobs on restart
- **10-second polling granularity**: Jobs may run up to 10 seconds late → Acceptable for MVP; cron jobs are typically hourly/daily
- **A2A call timeout**: Long-running agent skills may block the scheduler loop → Mitigation: 30-second HTTP timeout in `A2AClient::postJson()` already exists; scheduler moves to next job regardless
- **Schema migration on existing deployments**: New table requires migration → Standard Doctrine migration, no data loss risk

## Migration Plan

1. Add `dragonmantank/cron-expression` to `composer.json`
2. Create migration for `scheduled_jobs` table
3. Implement `ScheduledJobRepository` and `SchedulerService`
4. Implement `scheduler:run` command
5. Hook into install/uninstall/enable/disable lifecycle
6. Add admin UI page
7. Add `core-scheduler` service to `compose.core.yaml`
8. Run migration on deployment
9. Future: migrate news-maker from APScheduler to central scheduler (separate proposal)

## Open Questions

- Should the scheduler log each execution to `a2a_message_audit` (it already does via `A2AClient::invoke`)? Likely yes, no extra work needed.
- Should we add a `locked_at` / `locked_by` column for debugging stuck jobs? Deferred to v2 if needed.
