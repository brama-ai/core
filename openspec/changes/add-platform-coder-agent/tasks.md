# Tasks: add-platform-coder-agent

## 0. Foundation

- [ ] 0.1 Create `apps/core/src/CoderAgent/` namespace with base service classes
- [ ] 0.2 Add database migrations for `coder_tasks`, `coder_task_logs`, `coder_workers`
- [ ] 0.3 Register CoderAgent services in Symfony DI
- [ ] 0.4 Implement builder compatibility bridge for `builder/tasks/*` and `.opencode/pipeline/*`

## 1. Task Management

- [ ] 1.1 Implement `CoderTaskService` CRUD and status transitions
- [ ] 1.2 Implement DB-backed task claiming ordered by priority and creation time
- [ ] 1.3 Implement templates: `feature`, `bugfix`, `refactor`, `custom`
- [ ] 1.4 Implement task validation and builder markdown rendering
- [ ] 1.5 Add unit tests for task service and state transitions

## 2. Pipeline Runtime

- [ ] 2.1 Implement `BuilderPipelineRunner` around `builder/pipeline.sh`
- [ ] 2.2 Capture subprocess output and persist to `coder_task_logs`
- [ ] 2.3 Detect observed stage transitions from builder output and artifacts
- [ ] 2.4 Persist task summary path, branch, worktree path, and artifact metadata
- [ ] 2.5 Add unit tests for stage detection and runtime reconciliation

## 3. Workers

- [ ] 3.1 Implement `coder:worker:start`
- [ ] 3.2 Implement `coder:worker:stop`
- [ ] 3.3 Implement `coder:worker:status`
- [ ] 3.4 Implement worker registration, heartbeat, and dead-worker detection
- [ ] 3.5 Add unit tests for worker lifecycle and task claiming

## 4. Admin UI

- [ ] 4.1 Add admin routes under `/admin/coder`
- [ ] 4.2 Build task list dashboard with stats, workers, and recent activity
- [ ] 4.3 Build task creation form with templates and queue-now option
- [ ] 4.4 Build task detail page with stage timeline, logs, and artifacts
- [ ] 4.5 Add task actions: queue, cancel, retry, delete, priority update
- [ ] 4.6 Add sidebar navigation entry

## 5. Internal APIs and SSE

- [ ] 5.1 Add internal APIs for logs and workers
- [ ] 5.2 Add SSE endpoint for task and worker updates
- [ ] 5.3 Add browser-side EventSource integration for dashboard and detail views

## 6. Documentation and Validation

- [ ] 6.1 Update `builder/README.md` to describe Core-admin coexistence
- [ ] 6.2 Add operator-facing docs for the web UI
- [ ] 6.3 Validate OpenSpec change: `openspec validate add-platform-coder-agent --strict`
- [ ] 6.4 Run targeted tests and quality checks
