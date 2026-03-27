## Context

The scheduler (`SchedulerService::tick()`) currently calls `A2AClient::invoke()` sequentially per job. Each call uses `file_get_contents` with a 30-second timeout — a blocking I/O operation. With N due jobs, total tick time is up to `N × 30s`.

ReactPHP provides a non-blocking event loop and HTTP client that lets a single PHP process handle many concurrent HTTP requests without threads or extra processes.

## Goals / Non-Goals

- Goals:
  - All due jobs in a single tick dispatch their A2A calls concurrently
  - No additional Docker containers, queues, or infrastructure
  - Preserve existing retry/dead-letter logic, logging, and DB state management
  - Keep the synchronous `A2AClient` unchanged for web request use cases

- Non-Goals:
  - Replacing `A2AClient` globally — only the scheduler dispatch path changes
  - Streaming A2A responses — fire-and-wait is sufficient for scheduled jobs
  - Sub-second scheduling precision
  - Distributed/multi-instance scheduler

## Decisions

### 1. ReactPHP over Swoole, Messenger, or Node.js

- **Decision**: Use `react/http` + `react/async` for concurrent A2A dispatch
- **Why**:
  - **vs Messenger + RabbitMQ**: Messenger just offloads blocking to separate worker PHP processes. Need N workers for N parallel jobs — doesn't solve the fundamental I/O blocking, just spreads it.
  - **vs Swoole**: Requires a custom PHP extension, non-standard Docker base image, limited Symfony compatibility. Overkill for scheduler-only concurrency.
  - **vs Node.js/TypeScript**: Introduces a second language. Duplicates DB access logic or requires internal API calls. Operational complexity.
  - **ReactPHP**: Pure PHP, Composer-installable, single-process concurrency via event loop. Battle-tested (10+ years, used by Ratchet, Drift). Works with existing Doctrine DBAL connections.
- **Trade-off**: ReactPHP's promise-based style differs from standard Symfony request/response. Scoped to one class (`AsyncA2ADispatcher`) to contain complexity.

### 2. Dedicated AsyncA2ADispatcher (not modifying A2AClient)

- **Decision**: Create a new `AsyncA2ADispatcher` class specifically for the scheduler, rather than making `A2AClient` async-capable.
- **Why**: `A2AClient` is used by web controllers, admin APIs, and event bus — all synchronous contexts. Adding ReactPHP promises to it would pollute the entire call graph. The scheduler is the only component that benefits from concurrency. A dedicated class isolates the async boundary.
- **Interface**: `dispatchAll(array $jobs): array` — takes all due jobs, returns results array indexed by job ID.

### 3. Blocking bridge via React\Async\await

- **Decision**: Use `React\Async\await()` to bridge async promises back to synchronous code at the `tick()` level.
- **Why**: The `SchedulerRunCommand` loop is a classic `while(true) { tick(); sleep(); }`. Converting the entire command to event-loop-driven would be a larger refactor. `await()` lets us use async HTTP inside `tick()` while keeping the outer loop synchronous. This is the recommended ReactPHP pattern for mixing sync/async code.

### 4. Concurrency limit

- **Decision**: Add a configurable concurrency limit (default: 20 simultaneous A2A calls) to prevent overwhelming agents or network.
- **Why**: If 100 jobs are due after a scheduler restart, firing 100 HTTP requests simultaneously could saturate network or overload small agents. A semaphore-like limiter (via `clue/reactphp-mq` or manual `SplQueue`) caps parallelism.

### 5. Per-job timeout preserved

- **Decision**: Each async HTTP request keeps the existing 30-second timeout.
- **Why**: Consistent with current behavior. A single slow agent doesn't block others (they run concurrently), but the scheduler won't wait indefinitely for any one agent.

## Execution Flow (After)

```
tick():
  1. BEGIN TRANSACTION
  2. SELECT ... FOR UPDATE SKIP LOCKED (find due jobs)
  3. For each job: compute next_run_at, UPDATE scheduled_jobs
  4. COMMIT (jobs are now "dispatched" — next_run_at moved forward)
  5. asyncDispatcher.dispatchAll(jobs)
     ├── POST /a2a agent-1 (non-blocking)
     ├── POST /a2a agent-2 (non-blocking)
     ├── POST /a2a agent-3 (non-blocking)
     └── ... all in parallel, max 20 concurrent
  6. Collect results (await all promises)
  7. For each result: logFinish(), handle retry on failure
```

Key change: step 3 updates `next_run_at` **before** the A2A call (inside the transaction). This means even if the process crashes during step 5-7, the job won't be double-picked on next tick. The log entry with `status = 'running'` serves as crash detection.

## Risks / Trade-offs

- **ReactPHP memory in long-running process**: The event loop runs inside a long-lived PHP process. ReactPHP is designed for this, but Doctrine DBAL connections may need periodic ping/reconnect. Mitigation: the existing `sleep(10)` between ticks gives natural idle periods; add connection keep-alive check.
- **Error isolation**: If one promise rejection is unhandled, it could affect the event loop. Mitigation: wrap each promise in `.then()/.catch()` individually, never let an unhandled rejection propagate.
- **Testing**: Async code is harder to unit-test. Mitigation: `AsyncA2ADispatcher` has a simple interface; mock it entirely in `SchedulerService` tests. Integration tests can use `React\Async\await()` directly.
- **Doctrine DBAL not async**: DB writes after A2A calls are still synchronous. This is fine — DB writes are fast (< 1ms each), and we batch them after all HTTP calls complete.
