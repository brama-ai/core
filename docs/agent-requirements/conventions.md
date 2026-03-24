# Agent Platform Conventions

Every service that participates in the AI Community Platform agent ecosystem MUST implement the
conventions described here. These are the minimum contracts that allow `core` to discover,
register, verify, and manage agents automatically — without any agent-specific code in core.

---

## 1. Docker Compose Naming & Labels

Every agent service in `compose.yaml` MUST:

| Requirement | Value |
|---|---|
| Service name | End with `-agent` (e.g., `knowledge-agent`, `news-maker-agent`) |
| Docker label | `ai.platform.agent=true` |
| Docker label | `ai.platform.manifest.path=/api/v1/manifest` *(optional override, default assumed)* |

**Example:**
```yaml
services:
  knowledge-agent:
    build: ...
    labels:
      - "ai.platform.agent=true"
    networks:
      - platform
```

Core discovers agents by querying the Traefik API for services matching the `*-agent` naming
pattern. The Docker label provides an explicit opt-in for non-standard service names.

---

## 2. Agent Card Endpoint

**`GET /api/v1/manifest`** — REQUIRED, no authentication.

Returns the Agent Card — agent metadata used by core for registration, skill routing, and the A2A Gateway skill catalog.

### Minimum valid response (HTTP 200):

```json
{
  "name": "knowledge-agent",
  "version": "1.2.0",
  "url": "http://knowledge-agent/api/v1/knowledge/a2a",
  "skills": [
    { "id": "knowledge.search", "name": "Knowledge Search", "description": "Search the knowledge base" }
  ]
}
```

### Full response schema (aligned with official A2A AgentCard):

```json
{
  "name":               "string (required) — stable slug, kebab-case",
  "version":            "string (required) — semver e.g. 1.0.0",
  "description":        "string (recommended)",
  "url":                "string (URL) — A2A Server endpoint (replaces deprecated a2a_endpoint)",
  "provider":           { "organization": "string", "url": "string (URL)" },
  "capabilities":       { "streaming": false, "pushNotifications": false },
  "defaultInputModes":  ["text"],
  "defaultOutputModes": ["text"],
  "skills": [
    {
      "id":          "skill.name",
      "name":        "Human-Readable Name",
      "description": "What this skill does",
      "tags":        ["tag1"],
      "examples":    ["Example prompt"]
    }
  ],
  "skill_schemas": { "<skill-id>": { "input_schema": {} } },
  "permissions":        ["admin"],
  "commands":           ["/wiki"],
  "events":             ["message.created"],
  "health_url":         "string (URL, optional)",
  "admin_url":          "string (optional)",
  "storage":            {
    "postgres": {
      "db_name": "string",
      "user": "string",
      "password": "string",
      "startup_migration": {
        "enabled": true,
        "mode": "best_effort",
        "command": "php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration || true"
      }
    },
    "redis": {},
    "opensearch": {}
  }
}
```

### Field rules:

| Field | Required | Notes |
|---|---|---|
| `name` | ✅ | Stable identifier. Changing it creates a new agent in registry |
| `version` | ✅ | Must follow semver `MAJOR.MINOR.PATCH` |
| `url` | if skills ≠ [] | A2A Server endpoint URL (official A2A field). Legacy `a2a_endpoint` accepted |
| `skills` | recommended | Array of `AgentSkill` objects or legacy string IDs |
| `capabilities` | recommended | A2A protocol capabilities: `{ streaming, pushNotifications }` |
| `provider` | optional | `{ organization }` — service provider info |
| `health_url` | optional | Defaults to `http://<service-hostname>/health` |
| `admin_url` | optional | Shown as link in core admin panel |
| `skill_schemas` | deprecated | Fold input schemas into structured skills instead |
| `storage.postgres.startup_migration` | if `storage.postgres` exists | Startup migration contract. Must declare non-blocking command with `mode = best_effort` |

### Validation behavior in core:

| Agent Card state | Core behavior | Agent status |
|---|---|---|
| Valid, all required fields | Full registration | `healthy` |
| Valid but missing optional fields | Partial registration with warnings | `degraded` |
| `name` or `version` missing | Registration blocked | `error` |
| Connection refused / timeout | Not registered (or previous registration kept) | `unavailable` |
| Invalid JSON | Error stored, raw response saved | `error` |

---

## 3. Health Endpoint

**`GET /health`** — REQUIRED, no authentication.

```json
{"status": "ok"}
```

HTTP 200 always (even during degraded state — the agent is responsible for its own health logic).
Core uses this endpoint for liveness polling every 60 seconds.

### Inline health probe on registration

When an agent registers via `POST /api/v1/internal/agents/register` and the manifest includes a
`health_url` field, core performs an **immediate inline health probe** (2-second timeout) before
returning the registration response. If the probe succeeds (HTTP 2xx), `health_status` is set to
`healthy` in the database and the response includes `"health_status": "healthy"`. If the probe
fails or times out, `health_status` remains `unknown` and the periodic health poller will retry on
its next cycle.

This ensures that agents registering with a `health_url` get immediate health status resolution —
no waiting for the next poller cycle. E2E registration payloads MUST include `health_url` using
Docker DNS names (e.g., `http://hello-agent-e2e/health`) so that the core-e2e container can reach
the agent health endpoint directly.

---

## 4. A2A Endpoint

**`POST /api/v1/a2a`** — REQUIRED if `skills` is non-empty.

Standard request envelope:

```json
{
  "tool":       "search_knowledge",
  "input":      { "query": "..." },
  "trace_id":   "uuid",
  "request_id": "uuid"
}
```

Standard response envelope:

```json
{
  "status":  "completed | failed | needs_clarification",
  "output":  { ... },
  "error":   "string or null"
}
```

Rules:
- MUST return HTTP 200 even for business-level errors (use `status: "failed"`)
- MUST return HTTP 400/422 for malformed request envelopes
- MUST NOT return unstructured plain text as the primary response
- MUST handle unknown `tool` values with `status: "failed"` + descriptive `error`
- MUST be idempotent for the same `request_id`

### Accept-Language Header

Agents SHOULD read the `Accept-Language` header from incoming A2A requests to provide localized responses:

```php
$locale = $request->headers->get('Accept-Language', 'uk');
```

The platform core sets `Accept-Language` based on the user's `locale` cookie (`ua` → `uk`, `en` → `en`). Agents can use this header to:
- Respond inthe user's preferred language
- Format dates, numbers, and messages appropriately
- Select language-specific content

This is a **recommendation** (WARN level in auditor), not a hard requirement.

---

## 5. Inter-Agent Communication

Agents MUST NOT call other agents directly by their Docker service name (e.g. `http://knowledge-agent/...`).
All inter-agent communication MUST go through the A2A gateway in core via `PLATFORM_CORE_URL`:

```
POST {PLATFORM_CORE_URL}/api/v1/a2a/send-message
```

**Why:**

- Core acts as a skill router — it resolves which agent handles a given skill and proxies the request.
- Direct calls break E2E test isolation: E2E agents run as separate containers (`knowledge-agent-e2e`, `hello-agent-e2e`) and the direct URL would either hit a prod container or fail with DNS error.
- Core provides unified auth, tracing, logging, and rate limiting for all A2A traffic.

**Correct pattern:**

```php
// PHP: inject PLATFORM_CORE_URL from env
$response = $httpClient->request('POST', $platformCoreUrl . '/api/v1/a2a/send-message', [
    'json' => [
        'tool'       => 'knowledge.search',
        'input'      => ['query' => '...'],
        'trace_id'   => $traceId,
        'request_id' => $requestId,
    ],
]);
```

```python
# Python: read PLATFORM_CORE_URL from env
response = httpx.post(
    f"{platform_core_url}/api/v1/a2a/send-message",
    json={"tool": "knowledge.search", "input": {"query": "..."}, "trace_id": trace_id, "request_id": request_id},
)
```

**Violation:** Any hardcoded `http://<other-agent-service>/` URL in agent source code is a convention violation.

---

## 6. Convention Verification in Core

Core includes `AgentConventionVerifier` which checks all registered agents on demand and
on every discovery cycle. It reports violations per-agent:

```
VIOLATION [knowledge-agent]: url (or a2a_endpoint) missing but skills declared
VIOLATION [news-maker-agent]: version "1.0" does not match semver pattern
```

Violations are stored in the agent registry row and shown in the admin panel.
Admins can click a badge to view the full violation list.

---

## 7. State Model In Admin UI

Admin state rendering (runtime `enabled/disabled` + health/convention states) is defined in:

- `docs/agent-requirements/agent-state-model.md`

This state model is a contract for:

- operator-facing hint text,
- badge semantics,
- and stable selectors used by automated tests.

### Lifecycle actions (install -> enable -> settings -> delete)

Admin lifecycle for discovered agents is split into two tabs:

- `Встановлені`: agents with `installed_at != null`
- `Маркетплейс`: discoverable agents with `installed_at = null`

Allowed actions and order:

1. `POST /api/v1/internal/agents/{name}/install` — provisioning step (storage + migrations), does **not** enable traffic
2. `POST /api/v1/internal/agents/{name}/enable` — runtime activation; requires prior install
3. `GET /admin/agents/{name}/settings` — UI settings link is shown only for enabled installed agents
4. `DELETE /api/v1/internal/agents/{name}` — full deprovision (Postgres/Redis/OpenSearch cleanup), returns agent to marketplace if still discoverable

Postgres convention:

- install provisions both primary DB (`db_name`) and E2E DB (`test_db_name` or `<db_name>_test`)
- delete/deprovision removes both DBs and the declared DB role
- agent MUST run startup migrations on each container start in non-blocking mode (`startup_migration.mode = best_effort`)
- after code update, operators SHOULD run `docker compose restart <agent-service>` so startup migrations are applied

---

## 8. Adding a New Agent — Checklist

### In-repo agent (bundled under apps/)

1. Add service to `compose.agent-<name>.yaml` with name ending `-agent` and label `ai.platform.agent=true`
2. Implement `GET /api/v1/manifest` returning valid JSON
3. Implement `GET /health` returning `{"status": "ok"}`
4. If skills declared: implement `POST /api/v1/a2a`
5. Run `make conventions-test` — all checks must pass
6. Core auto-discovers on next discovery cycle (up to 60s) or via "Run Discovery" in admin panel

No manual registration required. No code changes in core needed.

### External agent (checked out under projects/)

1. Clone the agent repository: `make external-agent-clone repo=<url> name=<agent-name>`
2. Review `compose.fragments/<agent-name>.yaml` — verify labels, network, healthcheck
3. Configure secrets: `cp projects/<agent-name>/.env.local.example projects/<agent-name>/.env.local`
4. Start the agent: `make external-agent-up name=<agent-name>`
5. Verify health: `curl -s http://localhost:<port>/health`
6. Run discovery: `make agent-discover`
7. Install and enable in admin panel: **Agents → Marketplace → Install → Enable**

The same manifest, health, and A2A contracts apply regardless of source origin.

See `docs/guides/external-agents/` for the full onboarding guide.

---

## 9. External Agent Workspace Convention

External agents are maintained in separate repositories and checked out into the platform workspace
under `projects/<agent-name>/`. This is the canonical path for all externally maintained agents.

```
projects/
  hello-agent/          ← git clone of the hello-agent repository
  knowledge-agent/      ← git clone of the knowledge-agent repository
```

Compose fragments for external agents live in `compose.fragments/<agent-name>.yaml` and are
auto-discovered by the Makefile. Neither `projects/` nor `compose.fragments/*.yaml` are committed
to the platform repository — they are operator-local.

The platform tooling (`make external-agent-*`) manages the full lifecycle:

| Command | Description |
|---------|-------------|
| `make external-agent-list` | List detected external agent fragments |
| `make external-agent-clone repo=URL name=X` | Clone agent repo and install fragment |
| `make external-agent-up name=X` | Start/update a named external agent |
| `make external-agent-down name=X` | Stop a named external agent |
