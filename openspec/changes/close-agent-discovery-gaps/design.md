## Context

The `refactor-agent-discovery` change introduced Traefik-based pull discovery, convention
verification, a 4-state agent status machine, admin UI badges, and a convention test suite.
The `add-kubernetes-agent-discovery` change extended this with a provider strategy pattern
supporting both Docker Compose and Kubernetes runtimes.

Both changes are substantially implemented. However, an audit reveals gaps between the original
`refactor-agent-discovery` specification and the codebase. This design document covers the
decisions for closing those gaps.

## Goals / Non-Goals

- Goals:
  - Close all remaining gaps from `refactor-agent-discovery` tasks.md
  - Bring test coverage to the level specified in the original proposal
  - Enable automated 60s discovery polling (currently manual-only)
  - Create the JSON Schema file that was specified but never implemented
  - Complete admin UI with the "Add by URL" placeholder
  - Make `refactor-agent-discovery` archivable

- Non-Goals:
  - Refactoring existing working code (AgentCardFetcher naming, JS vs TS convention tests)
  - Implementing actual URL-based agent provisioning (future scope)
  - Cross-namespace Kubernetes discovery
  - Changing the convention test framework from REST to Playwright

## Decisions

### D1: JSON Schema file — create but keep inline validation as primary

- **Chosen**: Create `config/agent-manifest-schema.json` as the formal contract definition.
  The `AgentConventionVerifier` MAY optionally use it for validation, but the existing inline
  PHP validation logic is already working and well-tested. The schema file serves primarily as
  documentation and as the source for the test-side schema.
- **Why**: Introducing a JSON Schema validation library (e.g., `justinrainbow/json-schema`)
  adds a dependency for marginal benefit when inline validation already works. The schema file
  is still valuable as a contract artifact.
- **Alternative considered**: Replace inline validation with schema-based validation. Rejected —
  adds dependency, risk of regression, and the inline approach handles platform-specific rules
  (like postgres startup migration) that don't map cleanly to JSON Schema.

### D2: Scheduler integration — use existing platform scheduler pattern

- **Chosen**: Register `agent:discovery` in the platform's existing scheduling mechanism.
  The platform uses a custom `scheduler:run` command pattern. Add `agent:discovery` to the
  scheduled commands list with a 60-second interval.
- **Why**: Consistent with how other periodic tasks are scheduled in the platform.
- **Alternative considered**: Symfony Scheduler component. Rejected — the platform already has
  its own scheduling infrastructure; adding a second mechanism creates confusion.

### D3: "Add by URL" — minimal placeholder modal

- **Chosen**: Add a button labeled "Додати за URL" / "Add by URL" that opens a Bootstrap modal
  with a message explaining the feature is in development and instructions to add agents via
  `compose.yaml` manually.
- **Why**: Matches the original `refactor-agent-discovery` design spec exactly.
- **No backend needed**: The modal is purely informational.

### D4: Accept naming deviations as-is

- **Chosen**: Accept `AgentCardFetcher` (vs specified `AgentManifestFetcher`) and `.js` convention
  tests (vs specified `.ts`) as valid deviations. Document them in the tasks.md completion notes.
- **Why**: The implementations are functionally correct. Renaming would create unnecessary churn
  and risk regressions.

### D5: Convention test schema — mirror core schema

- **Chosen**: Create `tests/agent-conventions/support/manifest-schema.json` as a copy of
  `config/agent-manifest-schema.json`. The convention tests can optionally use it for
  schema-based assertions.
- **Why**: Matches the original task specification. Provides test-side validation capability
  without requiring the tests to reach into core's config directory.

## Risks / Trade-offs

- **Scheduler integration risk** — Adding `agent:discovery` to the scheduler means it runs
  automatically. If the Traefik API is slow or unresponsive, this could cause log noise.
  Mitigation: the existing discovery code already handles timeouts gracefully and logs warnings.
- **Schema drift** — Two copies of the manifest schema (core + tests) could drift apart.
  Mitigation: document the relationship; consider a symlink or build-time copy in the future.

## Open Questions

- None — all decisions are straightforward completions of the original specification.
