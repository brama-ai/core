# async-scheduler-dispatch

## Why

The current scheduler executes A2A calls **synchronously** inside the `tick()` loop using `file_get_contents` with a 30s timeout. Each job blocks until the agent responds. If 10 jobs are due simultaneously, the last job waits up to 5 minutes. As more agents declare scheduled jobs, this creates an ever-growing sequential queue.

A Symfony Messenger + RabbitMQ approach was considered but only moves the queue — each PHP worker process still blocks on one HTTP call, requiring N worker processes for N parallel jobs. This doesn't fundamentally solve the I/O-bound concurrency problem.

## Solution: ReactPHP Event Loop

Replace the blocking `file_get_contents` calls in the scheduler with non-blocking HTTP requests using `react/http` (ReactPHP HTTP client). A single PHP process can dispatch **all due jobs concurrently** using an event loop with async promises.

The `scheduler:run` command's `tick()` method becomes:
1. Find due jobs (unchanged — synchronous DB query)
2. Dispatch **all** A2A calls in parallel via `React\Http\Browser`
3. Collect results as promises resolve
4. Update job states and logs (synchronous DB writes, batched after all promises settle)

One process, zero extra containers, zero queue infrastructure. 50 concurrent A2A calls on a single scheduler instance.

## What Changes

- Install `react/http` and `react/async` in core (`composer require`)
- Create `AsyncA2ADispatcher` — a scheduler-specific async HTTP client wrapping `React\Http\Browser` that mirrors the A2A call signature
- Modify `SchedulerService::tick()` — replace sequential `A2AClient::invoke()` loop with parallel async dispatch via `React\Async\await(React\Promise\all(...))`
- Modify `SchedulerRunCommand` — create ReactPHP event loop once at startup, inject into the service

## What Does NOT Change

- `A2AClient` — the synchronous client stays as-is for all non-scheduler use (web requests, admin APIs)
- The `scheduled_jobs` and `scheduler_job_logs` tables — unchanged
- Admin UI, log viewer, visual cron builder — unchanged
- Retry/dead-letter policy — same logic, just runs after async results arrive
- Agent manifest registration — unchanged
- Docker topology — same `core-scheduler` container, no new services
- `FOR UPDATE SKIP LOCKED` concurrency control — unchanged

## Impact

- `apps/brama-core/composer.json` — 2 new dependencies (`react/http`, `react/async`)
- `apps/brama-core/src/Scheduler/AsyncA2ADispatcher.php` — new class (~80 lines)
- `apps/brama-core/src/Scheduler/SchedulerService.php` — `tick()` refactored to use async dispatch
- `apps/brama-core/src/Command/SchedulerRunCommand.php` — minor: pass loop to service
- Existing unit tests updated to mock `AsyncA2ADispatcher` instead of `A2AClient`
