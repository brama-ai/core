## Context

E2E tests assert that agents display a `badge-healthy` or `badge-degraded` CSS class in the admin agents table. After agent registration, the `health_status` column defaults to `'unknown'` (DB default from migration `Version20260304000002`). The health poller command (`app:agent-health-poll`) is the only mechanism that transitions agents out of `'unknown'`, but it requires `health_url` in the agent manifest — which E2E registration payloads omit.

**Affected stakeholders:** E2E test suite, pipeline CI, agent developers who rely on immediate health feedback after registration.

## Goals / Non-Goals

- **Goals:**
  - Agents registered via the internal API with a `health_url` SHALL have their `health_status` resolved immediately (not left as `unknown`)
  - E2E tests SHALL see `badge-healthy` or `badge-degraded` for all registered agents without requiring a separate health poll cron cycle
  - Fix SHALL be minimal and not change the existing health poller behavior for production cron-based polling

- **Non-Goals:**
  - Changing the Traefik-based discovery flow
  - Adding new health check protocols (gRPC, TCP)
  - Modifying the admin UI template logic (the template already handles all states correctly)

## Decisions

### Decision 1: Add `health_url` to E2E registration payloads

**What:** Include `health_url` field in all E2E agent registration payloads (Makefile and in-test Before hooks). Use Docker DNS names (e.g., `http://hello-agent-e2e/health`) since core-e2e runs in the same Docker network.

**Why:** The health poller skips agents without `health_url`. Without it, agents remain `unknown` forever unless discovered via Traefik (which uses `AgentConventionVerifier`, not actual health checks).

**Alternatives considered:**
- *Set `health_status` directly via SQL in `e2e-register-agents`*: Quick fix but masks the real problem — the platform should resolve health on registration. Rejected because it doesn't fix in-test registrations (news_maker_admin_test.js Before hook).
- *Run `app:agent-health-poll` as part of e2e-prepare*: Viable but adds latency and still doesn't fix in-test re-registrations. Used as a complementary measure.

### Decision 2: Inline health probe on registration

**What:** When `AgentRegistrationController` processes a registration with `health_url` present, it SHALL trigger a single health probe and update `health_status` before returning the response.

**Why:** This ensures that any agent registering with a `health_url` gets immediate health status resolution. This benefits both E2E and production scenarios where agents self-register on startup.

**Implementation approach:**
1. Extract the health check logic from `AgentHealthPollerCommand::checkHealth()` into a reusable service (e.g., `AgentHealthChecker`)
2. Inject `AgentHealthChecker` into `AgentRegistrationController`
3. After `register()`, if manifest contains `health_url`, call `checkHealth()` and update `health_status` accordingly
4. Return the resolved `health_status` in the registration response

**Alternatives considered:**
- *Fire-and-forget async health check via Messenger*: Adds complexity, still has timing issues for E2E. Rejected.
- *Always set `health_status = 'healthy'` on registration*: Incorrect — the agent might not actually be healthy. Rejected.

### Decision 3: Run health poll in e2e-prepare as safety net

**What:** Add `docker compose exec -T core-e2e php bin/console app:agent-health-poll` to the `e2e-prepare` Makefile target after `e2e-register-agents`.

**Why:** Belt-and-suspenders approach. Even with inline health probes, running the poller ensures agents discovered via Traefik also get their health resolved before tests start.

## Risks / Trade-offs

- **Risk:** Inline health probe adds latency to registration API (~5s timeout per agent if unreachable).
  - **Mitigation:** Use a short timeout (2s) for inline probes. The poller already uses 5s; inline can be more aggressive since we expect the agent to be available at registration time.

- **Risk:** Health probe during registration may fail if the agent container is still starting.
  - **Mitigation:** This is acceptable — `health_status` will be set to `unknown` (current behavior) and the poller will retry later. The E2E case is safe because `e2e-prepare` waits for containers to be healthy before registering.

- **Risk:** Extracting `checkHealth()` into a service changes existing code paths.
  - **Mitigation:** The extraction is purely structural (move method, no logic change). Existing `AgentHealthPollerCommand` will delegate to the new service.

## Component Interactions

```
E2E Makefile (e2e-register-agents)
  → POST /api/v1/internal/agents/register (with health_url)
    → AgentRegistrationController
      → ManifestValidator.validate()
      → AgentRegistryRepository.register() (INSERT with health_status='unknown')
      → AgentHealthChecker.check(health_url) → HTTP GET to agent /health
      → AgentRegistryRepository.updateHealthStatus(name, 'healthy'|'unknown')
    ← Response: {status: 'registered', health_status: 'healthy'}

E2E Makefile (e2e-prepare)
  → docker compose exec core-e2e php bin/console app:agent-health-poll
    → AgentHealthPollerCommand
      → AgentHealthChecker.check(health_url) for each agent
      → Updates health_status in DB

E2E Test (hello_agent_test.js)
  → agentsPage.open()
  → agentsPage.seeAgentHealthyLike('hello-agent')
    → XPath: //tr[contains(@data-agent-name,"hello-agent")]//span[contains(@class,"badge-healthy")]
    → ✅ Found because health_status was set during registration
```

## Open Questions

- None — the approach is straightforward and all components are well-understood.
