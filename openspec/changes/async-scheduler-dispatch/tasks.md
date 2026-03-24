## 1. Dependencies

- [x] 1.1 `composer require react/http react/async` in `apps/brama-core/` — installed `react/http ^1.11`, `react/async ^4.3` (with `-W` to allow `psr/http-message` downgrade to v1.1)
- [x] 1.2 Verify no conflicts with Symfony 7 / PHP 8.5 — PHPStan passes with 0 errors

## 2. AsyncA2ADispatcher

- [x] 2.1 Create `App\Scheduler\AsyncA2ADispatcher` class
  - Constructor: `AgentRegistryInterface`, `LoggerInterface`, `string $internalToken`, `int $concurrencyLimit = 20`, `float $timeout = 30.0`
  - Method: `dispatchAll(array $jobs): array` — takes list of job arrays, returns results indexed by job ID
  - Uses `React\Http\Browser` with `React\Socket\Connector` (30s timeout)
  - Resolves agent A2A endpoint from registry (same logic as `A2AClient`)
  - Builds request payload matching A2A protocol (traceId, requestId, headers)
  - Limits concurrency via promise queue (max N in-flight requests)
  - Each promise: on success → `['status' => 'completed', 'result' => ...]`; on failure → `['status' => 'failed', 'error' => ...]`
  - Extracted `AsyncA2ADispatcherInterface` for testability (final class can't be mocked)
- [x] 2.2 Register as Symfony service (autowired, inject `$internalToken` from `%app.internal_token%`, interface alias)
- [x] 2.3 Add `SCHEDULER_CONCURRENCY_LIMIT` env var (default 20) in `.env`

## 3. Refactor SchedulerService::tick()

- [x] 3.1 Replace `A2AClient` dependency with `AsyncA2ADispatcherInterface` in `SchedulerService` constructor
  - `A2AClient` remains unchanged for non-scheduler code paths
- [x] 3.2 Split `tick()` into two phases:
  - **Phase 1 (transactional):** Find due jobs, log starts, compute next_run_at, update DB with `running` status, commit
  - **Phase 2 (async):** Call `asyncDispatcher->dispatchAll($jobs)`, process results, log finishes, handle retries
- [x] 3.3 `next_run_at` is updated inside the transaction (before async dispatch) to prevent double-pick on crash
- [x] 3.4 After dispatch: iterate results, call `jobLog->logFinish()` and `handleFailure()` per job

## 4. SchedulerRunCommand adjustments

- [x] 4.1 No structural changes needed — `React\Async\await()` is called inside `dispatchAll()`, transparent to the command loop
- [x] 4.2 Add connection keepalive check before each tick (`SELECT 1` ping, `close()` on failure for lazy reconnect)

## 5. Tests

- [x] 5.1 Unit test: `AsyncA2ADispatcherTest` — 4 tests: empty dispatch, unknown skill, agent resolution, error isolation
- [x] 5.2 Update `SchedulerServiceTest` — mock `AsyncA2ADispatcherInterface`; 13 tests including Phase 1 commit-before-dispatch, next_run_at-before-dispatch, multiple jobs dispatched together
- [x] 5.3 Integration test: verify `dispatchAll()` with real ReactPHP event loop and mock HTTP server — added `AsyncA2ADispatcherIntegrationTest` with 5 test scenarios

## 6. Documentation

- [x] 6.1 Update `docs/scheduler.md` — added "Async Dispatch" section explaining concurrency model, two-phase tick, error isolation
- [x] 6.2 Documented `SCHEDULER_CONCURRENCY_LIMIT` env var, ReactPHP dependencies, per-job timeout

## 7. Quality Checks

- [x] 7.1 Run `phpstan analyse` — 0 errors
- [x] 7.2 Run `php-cs-fixer check` — 0 violations
- [x] 7.3 Run `codecept run Unit Scheduler` — all 23 tests pass (100 assertions)
- [x] 7.4 Run E2E `@scheduler` tests — 17 passed, 1 skipped (intentional xScenario); fixed XPath selector in scheduler_logs_test.js (contains(text(),...) → contains(.,...) for mixed-content span)
