## MODIFIED Requirements

### Requirement: Scheduler Polling Command (async dispatch)

The scheduler `tick()` SHALL dispatch all due A2A calls concurrently using a non-blocking HTTP client (ReactPHP), instead of invoking them sequentially. A single scheduler process SHALL handle multiple simultaneous agent calls.

#### Scenario: Multiple due jobs execute concurrently

- **WHEN** 5 jobs are due simultaneously, each with a 10-second agent response time
- **THEN** all 5 A2A calls are dispatched in parallel
- **AND** the total tick duration is approximately 10 seconds (not 50)

#### Scenario: Concurrency limit is respected

- **WHEN** 50 jobs are due simultaneously and the concurrency limit is 20
- **THEN** at most 20 A2A calls are in-flight at the same time
- **AND** remaining jobs are dispatched as in-flight calls complete

#### Scenario: Per-job timeout is preserved

- **WHEN** an async A2A call exceeds 30 seconds
- **THEN** that call is cancelled with a timeout error
- **AND** other concurrent calls are not affected

#### Scenario: Individual job failure does not affect others

- **WHEN** one A2A call in a batch fails (timeout, connection error, agent error)
- **THEN** the failed job follows the existing retry/dead-letter policy
- **AND** all other concurrent jobs continue to completion independently

#### Scenario: Job state is updated before dispatch

- **WHEN** due jobs are found in the `tick()` transaction
- **THEN** `next_run_at` is computed and updated inside the transaction (before A2A calls)
- **AND** the transaction is committed before async dispatch begins
- **SO THAT** a process crash during A2A calls does not cause duplicate execution on restart

#### Scenario: Execution logs track async timing

- **WHEN** jobs are dispatched concurrently
- **THEN** each job's `scheduler_job_logs` entry has its own `started_at` (dispatch time) and `finished_at` (response time)
- **AND** log entries accurately reflect per-job duration, not total batch duration

## ADDED Requirements

### Requirement: Async A2A Dispatcher

The platform SHALL provide an `AsyncA2ADispatcher` class that uses `React\Http\Browser` to perform non-blocking HTTP POST requests to agent A2A endpoints. This class SHALL be used exclusively by the scheduler.

#### Scenario: Dispatcher resolves agent endpoint from skill ID

- **WHEN** the dispatcher receives a job with `skill_id`
- **THEN** it resolves the agent's A2A endpoint URL via the agent registry
- **AND** sends a POST request with the same payload format as `A2AClient::invoke()`

#### Scenario: Dispatcher returns structured results

- **WHEN** all async calls in a batch complete (success or failure)
- **THEN** the dispatcher returns an array indexed by job ID
- **AND** each entry contains either a success result (status, response) or failure details (error message)

### Requirement: ReactPHP Dependencies

The core application SHALL include `react/http` and `react/async` as Composer dependencies for the async scheduler dispatch capability.

#### Scenario: Dependencies are installable

- **WHEN** `composer install` is run in the core app
- **THEN** `react/http` and `react/async` are installed without conflicts with existing Symfony 7 dependencies
