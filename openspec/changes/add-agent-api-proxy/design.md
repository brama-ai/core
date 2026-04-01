# Design: Agent API Proxy

## Context

Agents expose public HTTP endpoints that external services need to reach (e.g., Telegram webhook callbacks, OAuth callbacks). Currently, each agent would need its own subdomain and Cloudflare Tunnel route, which doesn't scale and complicates infrastructure management.

The platform already has a reverse-proxy pattern: the A2A Gateway in `App\A2AGateway` accepts inbound A2A requests and forwards them to agents. The Agent API Proxy extends this pattern to arbitrary HTTP endpoints declared by agents.

Stakeholders: platform operators (infrastructure simplification), agent developers (zero-config public endpoint exposure).

## Goals / Non-Goals

Goals:
- Single-domain routing: all agent public endpoints reachable via `brama.dev/api/agents/{name}/{path}`
- Auto-discovery: endpoints declared in agent manifests, no manual routing config
- Security boundary: Core validates agent existence, enabled status, and endpoint declaration before proxying
- Transparent proxying: request body, headers, and response forwarded as-is

Non-Goals:
- Authentication/rate-limiting of proxied requests (agents handle their own auth)
- WebSocket proxying (HTTP only for now)
- Request/response transformation or middleware hooks
- Caching of proxied responses

## Decisions

### Decision 1: Proxy lives in Core, not Traefik

The proxy controller lives in the Symfony application (`AgentProxyController`) rather than as Traefik middleware or a separate sidecar.

**Rationale**: Core already has the agent registry, health status, and manifest data needed to validate requests. Putting the proxy in Traefik would require syncing endpoint data to Traefik labels/config, adding complexity. The Symfony HttpClient is sufficient for HTTP proxying.

**Alternatives considered**:
- Traefik dynamic routing via labels: rejected because endpoint data lives in the DB, not in Docker labels; would require a custom Traefik provider
- Dedicated proxy sidecar (e.g., Envoy): over-engineered for current scale; adds operational complexity

### Decision 2: Catch-all route with database validation

Route: `GET|POST|PUT|DELETE /api/agents/{agentName}/{path}` where `{path}` is a catch-all parameter.

Validation order:
1. Agent exists in registry -> 404
2. Agent is enabled -> 503
3. Path is in agent's `public_endpoints` -> 403
4. HTTP method is allowed for that path -> 405
5. Forward request to agent

**Rationale**: A single catch-all route is simpler than dynamically registering routes per agent. The validation chain provides clear, specific error responses.

### Decision 3: Full-sync endpoint refresh

When an agent's manifest changes (detected during health poll), the platform replaces all stored `agent_public_endpoints` rows for that agent with the new set. This is a delete-and-reinsert strategy, not a merge.

**Rationale**: Endpoints are cheap to store and infrequently changed. Full sync avoids complex diffing logic and ensures the DB always matches the manifest exactly.

### Decision 4: Separate table for public endpoints

Public endpoints are stored in a dedicated `agent_public_endpoints` table rather than a JSONB column on `agent_registry`.

**Rationale**: A separate table enables efficient lookups by `(agent_id, path)` with a unique constraint, supports cascade deletes, and keeps the agent registry row clean. The query pattern (lookup by agent name + path) benefits from a normalized schema.

### Decision 5: Unauthenticated proxy route

The `/api/agents/{agentName}/{path}` route bypasses platform authentication (Symfony firewall, edge-auth middleware). External services like Telegram cannot authenticate with the platform.

**Rationale**: Agents are responsible for their own request authentication (e.g., Telegram webhook secret verification). The proxy only validates that the endpoint is declared — it does not authenticate the caller.

## Component Interactions

```
                                    ┌─────────────────────┐
Internet ──► Cloudflare Tunnel ──► │  Traefik (ingress)   │
                                    └─────────┬───────────┘
                                              │
                              /api/agents/{name}/{path}
                                              │
                                    ┌─────────▼───────────┐
                                    │  AgentProxyController │
                                    │  (Symfony)            │
                                    └─────────┬───────────┘
                                              │
                              1. Lookup agent in registry
                              2. Check enabled status
                              3. Validate path in public_endpoints
                              4. Validate HTTP method
                                              │
                                    ┌─────────▼───────────┐
                                    │  Symfony HttpClient   │
                                    │  (forward request)    │
                                    └─────────┬───────────┘
                                              │
                                    ┌─────────▼───────────┐
                                    │  Agent service        │
                                    │  (e.g., telegram-     │
                                    │   channel-agent:80)   │
                                    └─────────────────────┘
```

Affected components:
- **AgentProxyController** (new): `src/Controller/Api/AgentProxyController.php`
- **AgentPublicEndpoint** (new entity): `src/Entity/AgentPublicEndpoint.php`
- **AgentPublicEndpointRepository** (new): `src/Repository/AgentPublicEndpointRepository.php`
- **AgentCardFetcher** (modified): parse `public_endpoints` from manifest
- **Health poll sync** (modified): refresh public endpoints on manifest change
- **Agent Card schema** (modified): add optional `public_endpoints` array
- **Symfony security** (modified): allow unauthenticated access to `/api/agents/` prefix
- **Admin templates** (modified): display public endpoints on agent detail page

## Data Model

### New table: `agent_public_endpoints`

| Column       | Type         | Constraints                          |
|-------------|-------------|--------------------------------------|
| `id`        | SERIAL      | PRIMARY KEY                          |
| `agent_id`  | UUID        | FK -> agent_registry(id) ON DELETE CASCADE |
| `path`      | VARCHAR(255)| NOT NULL                             |
| `methods`   | JSON        | NOT NULL (e.g., `["POST"]`)          |
| `description`| TEXT       | NULLABLE                             |
| `created_at`| TIMESTAMP   | NOT NULL DEFAULT NOW()               |
| `updated_at`| TIMESTAMP   | NOT NULL DEFAULT NOW()               |

**Unique constraint**: `(agent_id, path)`

**Index**: Primary lookup is by agent name + path, so the join with `agent_registry` on name + the unique constraint covers the query pattern.

## API Surface

### New route

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| `GET\|POST\|PUT\|DELETE` | `/api/agents/{agentName}/{path}` | None (public) | Proxy to agent endpoint |

### Modified: Agent Card schema

New optional field in `config/agent-card.schema.json`:

```json
{
  "public_endpoints": {
    "type": "array",
    "items": {
      "type": "object",
      "required": ["path", "methods"],
      "properties": {
        "path": { "type": "string", "pattern": "^/" },
        "methods": { "type": "array", "items": { "type": "string", "enum": ["GET", "POST", "PUT", "DELETE"] } },
        "description": { "type": "string" }
      }
    }
  }
}
```

## Risks / Trade-offs

- **Open proxy abuse**: An attacker could probe agent endpoints through the proxy. Mitigation: only declared endpoints are proxied; undeclared paths return 403. Agents must validate their own auth.
- **Agent latency amplification**: Proxy adds one extra hop. Mitigation: configurable timeout (default 30s); 502/504 responses for failures.
- **Endpoint declaration drift**: If an agent's manifest is stale, endpoints may be out of sync. Mitigation: full-sync on every health poll; endpoints are refreshed regularly.
- **DB lookup per request**: Every proxied request requires a DB lookup. Mitigation: the lookup is a simple indexed query; at current scale this is negligible. Future: add Redis cache if needed.

## Migration Plan

1. Create `agent_public_endpoints` table via Doctrine migration
2. Deploy Core with new controller and schema changes
3. Update agent manifests to include `public_endpoints` (starting with telegram-channel-agent)
4. Health poll picks up new endpoints automatically
5. Reconfigure Telegram webhook URL to point to `brama.dev/api/agents/telegram-channel-agent/webhook/telegram`

Rollback: drop the `agent_public_endpoints` table and remove the route; agents fall back to direct access (if configured).

## Open Questions

None — the design is straightforward and follows established patterns.
