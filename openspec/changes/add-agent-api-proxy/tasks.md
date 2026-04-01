# Tasks — Agent API Proxy

## Phase 1: Schema & Discovery

- [ ] Add `public_endpoints` to Agent Card JSON schema (`config/agent-card.schema.json`)
- [ ] Create Doctrine entity `AgentPublicEndpoint` with migration
- [ ] Update `AgentCardFetcher` to parse `public_endpoints` from manifest
- [ ] Update health poll sync to refresh public endpoints on manifest change
- [ ] Add cascade delete of endpoints when agent is removed

## Phase 2: Proxy Controller

- [ ] Create `AgentProxyController` at `/api/agents/{agentName}/{path}`
- [ ] Implement endpoint validation (agent exists, enabled, path declared, method allowed)
- [ ] Implement HTTP proxy using Symfony HttpClient (forward body, headers, return response)
- [ ] Add configurable timeout via `AGENT_PROXY_TIMEOUT` env var (default 30s)
- [ ] Add proxy error handling (agent down → 502, timeout → 504)

## Phase 3: Security

- [ ] Configure Symfony security to allow unauthenticated access to `/api/agents/` prefix
- [ ] Ensure edge-auth middleware in Traefik does not block `/api/agents/` path
- [ ] Forward relevant headers (Content-Type, custom agent headers like X-Telegram-Bot-Api-Secret-Token)

## Phase 4: Admin UI

- [ ] Display "Public Endpoints" section on agent detail page in admin
- [ ] Show full proxy URL for each endpoint (copy-friendly)

## Phase 5: Tests

### Unit tests (PHPUnit)
- [ ] `AgentPublicEndpoint` entity: getters, setters, JSON serialization
- [ ] `AgentCardFetcher`: parses `public_endpoints` from manifest JSON, handles missing/empty/malformed field
- [ ] `AgentProxyController`: endpoint validation logic (agent not found → 404, disabled → 503, path not declared → 403, method not allowed → 405)

### Functional tests (Symfony WebTestCase / Cest)
- [ ] `POST /api/agents/hello/webhook` → 403 (endpoint not declared)
- [ ] `POST /api/agents/nonexistent/anything` → 404
- [ ] `POST /api/agents/disabled-agent/webhook` → 503
- [ ] `POST /api/agents/telegram-channel-agent/webhook/telegram` → proxies to agent, returns agent response
- [ ] Proxy forwards request body and Content-Type header
- [ ] Proxy returns 502 when agent is unreachable
- [ ] Health poll sync: agent with `public_endpoints` in manifest → rows created in DB
- [ ] Health poll sync: agent removes endpoint from manifest → row deleted from DB

### E2E tests (Playwright)
- [ ] Admin agents page shows "Public Endpoints" section for agent with endpoints
- [ ] Public endpoint row displays full proxy URL

## Phase 6: Agent manifest update (telegram-channel-agent)

- [ ] Add `public_endpoints` to telegram-channel-agent manifest
- [ ] Verify Telegram webhook delivery through proxy
