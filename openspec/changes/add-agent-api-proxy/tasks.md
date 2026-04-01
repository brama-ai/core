# Tasks — Agent API Proxy

## Phase 1: Schema & Discovery

- [x] 1.1 Add `public_endpoints` to Agent Card JSON schema (`config/agent-card.schema.json`)
- [x] 1.2 Create Doctrine entity `AgentPublicEndpoint` with migration
- [x] 1.3 Update `AgentCardFetcher` to parse `public_endpoints` from manifest
- [x] 1.4 Update health poll sync to refresh public endpoints on manifest change
- [x] 1.5 Add cascade delete of endpoints when agent is removed

## Phase 2: Proxy Controller

- [x] 2.1 Create `AgentProxyController` at `/api/agents/{agentName}/{path}`
- [x] 2.2 Implement endpoint validation (agent exists, enabled, path declared, method allowed)
- [x] 2.3 Implement HTTP proxy using Symfony HttpClient (forward body, headers, return response)
- [x] 2.4 Add configurable timeout via `AGENT_PROXY_TIMEOUT` env var (default 30s)
- [x] 2.5 Add proxy error handling (agent down -> 502, timeout -> 504)

## Phase 3: Security

- [x] 3.1 Configure Symfony security to allow unauthenticated access to `/api/agents/` prefix
- [x] 3.2 Ensure edge-auth middleware in Traefik does not block `/api/agents/` path
- [x] 3.3 Forward relevant headers (Content-Type, custom agent headers like X-Telegram-Bot-Api-Secret-Token)

## Phase 4: Admin UI

- [x] 4.1 Display "Public Endpoints" section on agent detail page in admin
- [x] 4.2 Show full proxy URL for each endpoint (copy-friendly)

## Phase 5: Tests

### Unit tests (PHPUnit)
- [x] 5.1 `AgentPublicEndpoint` entity: getters, setters, JSON serialization
- [x] 5.2 `AgentCardFetcher`: parses `public_endpoints` from manifest JSON, handles missing/empty/malformed field
- [x] 5.3 `AgentProxyController`: endpoint validation logic (agent not found -> 404, disabled -> 503, path not declared -> 403, method not allowed -> 405)

### Functional tests (Symfony WebTestCase / Cest)
- [x] 5.4 `POST /api/agents/hello/webhook` -> 403 (endpoint not declared)
- [x] 5.5 `POST /api/agents/nonexistent/anything` -> 404
- [x] 5.6 `POST /api/agents/disabled-agent/webhook` -> 503
- [ ] 5.7 `POST /api/agents/telegram-channel-agent/webhook/telegram` -> proxies to agent, returns agent response
- [x] 5.8 Proxy forwards request body and Content-Type header
- [x] 5.9 Proxy returns 502 when agent is unreachable
- [x] 5.10 Health poll sync: agent with `public_endpoints` in manifest -> rows created in DB
- [x] 5.11 Health poll sync: agent removes endpoint from manifest -> row deleted from DB

### E2E tests (Playwright)
- [ ] 5.12 Admin agents page shows "Public Endpoints" section for agent with endpoints
- [ ] 5.13 Public endpoint row displays full proxy URL

## Phase 6: Agent manifest update (telegram-channel-agent)

- [x] 6.1 Add `public_endpoints` to telegram-channel-agent manifest
- [ ] 6.2 Verify Telegram webhook delivery through proxy

## Phase 7: Documentation

- [x] 7.1 Update `docs/agent-requirements/conventions.md` with `public_endpoints` manifest field documentation
- [x] 7.2 Add agent proxy usage guide to `docs/` (developer-facing, English)
- [x] 7.3 Update `docs/agent-requirements/test-cases.md` if new convention test cases are needed

## Phase 8: Quality Checks

- [x] 8.1 `phpstan analyse` passes at level 8
- [x] 8.2 `php-cs-fixer check` passes with no violations
- [x] 8.3 `codecept run` passes all unit + functional suites
- [x] 8.4 `make conventions-test` passes (if applicable)
