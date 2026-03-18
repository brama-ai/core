# Change: Auto-cleanup stale marketplace agents

## Why
Agents discovered via Traefik (e.g. ephemeral E2E test containers) remain in the marketplace tab indefinitely even when the container is long gone and the agent was never installed. This clutters the admin UI with dozens of unreachable phantom entries.

## What Changes
- Extend the health-poll cycle to automatically hard-delete agents from `agent_registry` when:
  - The agent was **never installed** (`installed_at IS NULL`)
  - The agent has been **unreachable** for a configurable number of consecutive health-check failures (default: 5)
- Add a repository method `deleteStaleMarketplaceAgents()` that performs the bulk cleanup in a single query
- Log every auto-deletion via the existing audit mechanism

## Impact
- Affected specs: `agent-registry` (new requirement for stale cleanup)
- Affected code:
  - `AgentHealthPollerCommand` — call cleanup after the poll loop
  - `AgentRegistryRepository` — new `deleteStaleMarketplaceAgents()` method
  - `AgentRegistryInterface` — add method signature
