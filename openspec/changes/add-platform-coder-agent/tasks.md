# Tasks: add-platform-coder-agent

## 0. Foundation

- [ ] 0.1 Create `apps/core/src/CoderAgent/` namespace with base service classes
- [ ] 0.2 Add database migrations for `coder_tasks`, `coder_task_logs`, `coder_workers` tables
- [ ] 0.3 Register CoderAgent services in Symfony DI container
- [ ] 0.4 Add Redis queue configuration for task priority queue

## 1. Task Management Service

- [ ] 1.1 Implement `CoderTaskService` with CRUD operations for tasks
- [ ] 1.2 Implement task state machine (todo -> queued -> in_progress -> done/failed/cancelled)
- [ ] 1.3 Implement priority queue: enqueue tasks to Redis sorted set ordered by priority and creation time
- [ ] 1.4 Implement task template system with predefined templates (ADR, HLD, feature, bugfix, refactor)
- [ ] 1.5 Add input validation for task creation and updates
- [ ] 1.6 Add unit tests for task service and state transitions

## 2. Pipeline Orchestrator

- [ ] 2.1 Implement `PipelineOrchestrator` service that runs the 5-stage pipeline for a given task
- [ ] 2.2 Implement stage execution via subprocess (AI coding tool invocation with timeout and output capture)
- [ ] 2.3 Implement stage gate system: post-stage verification checks per stage (OpenSpec validate, PHPStan, CS Fixer, Codeception, doc check)
- [ ] 2.4 Implement model selection with fallback chains via LiteLLM gateway configuration
- [ ] 2.5 Implement retry logic per stage with configurable max retries and backoff
- [ ] 2.6 Implement handoff file generation between stages (passing context from one stage to the next)
- [ ] 2.7 Persist stage progress and logs to `coder_task_logs` table
- [ ] 2.8 Add unit tests for pipeline orchestration, gate checks, and retry logic

## 3. Git Worktree Management

- [ ] 3.1 Implement `GitWorktreeManager` service: create, track, and remove worktrees
- [ ] 3.2 Implement branch naming convention: `pipeline/<task-slug>` with slug generation from task title
- [ ] 3.3 Implement worktree dependency installation (composer install in worktree)
- [ ] 3.4 Implement cleanup: auto-remove worktree on task success, preserve on failure
- [ ] 3.5 Implement stale worktree detection and scheduled cleanup command
- [ ] 3.6 Add unit tests for worktree lifecycle and slug generation

## 4. Worker Management

- [ ] 4.1 Implement `coder:worker:start` Symfony command that polls the Redis queue and processes tasks
- [ ] 4.2 Implement `coder:worker:stop` command for graceful shutdown
- [ ] 4.3 Implement `coder:worker:status` command for listing active workers
- [ ] 4.4 Implement worker heartbeat (update `last_heartbeat_at` every 30 seconds)
- [ ] 4.5 Implement worker registration and deregistration in `coder_workers` table
- [ ] 4.6 Implement dead worker detection (no heartbeat for > 2 minutes)
- [ ] 4.7 Implement configurable max worker count via admin settings
- [ ] 4.8 Add unit tests for worker lifecycle and queue consumption

## 5. Admin UI -- Task Management

- [ ] 5.1 Add admin controller `CoderTaskController` with routes under `/admin/coder/`
- [ ] 5.2 Build task list page: sortable/filterable table with status, priority, stage, timing
- [ ] 5.3 Build task creation form with template selector and markdown description editor
- [ ] 5.4 Build task edit form: update description, priority, pipeline config
- [ ] 5.5 Build task detail page: current status, stage progress timeline, log viewer
- [ ] 5.6 Add task actions: queue, cancel, retry failed, delete
- [ ] 5.7 Add priority adjustment controls (promote/demote) on task list
- [ ] 5.8 Add admin navigation entry for "Coder" section in sidebar

## 6. Admin UI -- Dashboard and Monitoring

- [ ] 6.1 Build coder dashboard page: active workers, queue depth, recent completions/failures
- [ ] 6.2 Implement SSE endpoint `GET /admin/coder/events` for real-time updates
- [ ] 6.3 Add JavaScript SSE client to dashboard and task detail pages for live updates
- [ ] 6.4 Build worker management panel: list workers, start/stop controls, current task display
- [ ] 6.5 Build log viewer component: filterable by task, stage, and level; auto-scrolling
- [ ] 6.6 Add pipeline stage progress visualization (timeline/stepper component)

## 7. A2A Integration

- [ ] 7.1 Implement `coder.submit_task` A2A skill handler
- [ ] 7.2 Implement `coder.task_status` A2A skill handler
- [ ] 7.3 Implement `coder.cancel_task` A2A skill handler
- [ ] 7.4 Register skills in the agent card / skill catalog
- [ ] 7.5 Add unit tests for A2A skill handlers

## 8. Stage Gate Implementation

- [ ] 8.1 Implement architect gate: verify OpenSpec proposal files exist and pass `openspec validate --strict`
- [ ] 8.2 Implement coder gate: verify code changes present, no PHP syntax errors
- [ ] 8.3 Implement validator gate: run `phpstan analyse` at level 8, run `php-cs-fixer check`
- [ ] 8.4 Implement tester gate: run `codecept run` and verify all suites pass
- [ ] 8.5 Implement documenter gate: verify documentation files were created or updated
- [ ] 8.6 Add configurable gate strictness (warn vs fail) per task

## 9. Documentation

- [ ] 9.1 Update or create developer documentation for the Coder Agent in `docs/`
- [ ] 9.2 Add runbook for worker management, troubleshooting, and worktree cleanup
- [ ] 9.3 Add `.en.md` mirror for any Ukrainian user-facing docs added
- [ ] 9.4 Update `docs/agent-requirements/` if agent contracts changed

## 10. Quality Checks

- [ ] 10.1 Run `phpstan analyse` -- zero errors at level 8
- [ ] 10.2 Run `php-cs-fixer check` -- no style violations
- [ ] 10.3 Run `codecept run` -- all unit + functional suites pass
- [ ] 10.4 Run `make e2e` -- Playwright E2E passes (if applicable)
- [ ] 10.5 Validate OpenSpec change: `openspec validate add-platform-coder-agent --strict`
