## 1. Implementation

- [x] 1.1 Add `deleteStaleMarketplaceAgents(int $failureThreshold): int` to `AgentRegistryInterface`
- [x] 1.2 Implement the method in `AgentRegistryRepository` — single DELETE query: `installed_at IS NULL AND health_check_failures >= :threshold`
- [x] 1.3 Add audit log entries for each deleted agent (batch insert or loop)
- [x] 1.4 Call `deleteStaleMarketplaceAgents()` at the end of `AgentHealthPollerCommand::execute()`, after the poll loop completes
- [x] 1.5 Make the stale threshold configurable via constant (default 5), separate from the existing `FAILURE_THRESHOLD` (3) used for marking unavailable

## 2. Testing

- [x] 2.1 Unit test: `deleteStaleMarketplaceAgents` deletes agents with `installed_at = NULL` and failures >= threshold
- [x] 2.2 Unit test: installed agents are NOT deleted regardless of failure count
- [x] 2.3 Unit test: marketplace agents below threshold are NOT deleted
- [x] 2.4 Functional test: health poller command triggers cleanup after poll loop

## 3. Documentation

- [x] 3.1 Update `docs/features/` if agent registry docs exist

## 4. Quality Checks

- [x] 4.1 `make analyse` — PHPStan level 8, zero errors
- [x] 4.2 `make cs-check` — no style violations
- [x] 4.3 `make test` — all suites pass
