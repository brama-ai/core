# Agent API Proxy

The Agent API Proxy allows agents to expose public HTTP endpoints (e.g., Telegram webhook callbacks, OAuth callbacks) through a single platform domain without requiring per-agent subdomain configuration.

## How It Works

1. An agent declares `public_endpoints` in its manifest (`GET /api/v1/manifest`)
2. Core discovers and stores these declarations in the `agent_public_endpoints` table during health polling or discovery
3. External services send requests to `https://<platform-domain>/api/agents/{agentName}/{path}`
4. Core validates the request (agent exists, is enabled, path is declared, method is allowed) and forwards it to the agent's internal service

## Declaring Public Endpoints in a Manifest

Add a `public_endpoints` array to your agent's manifest response:

```json
{
  "name": "my-agent",
  "version": "1.0.0",
  "public_endpoints": [
    {
      "path": "/webhook/telegram",
      "methods": ["POST"],
      "description": "Telegram Bot API webhook receiver"
    }
  ]
}
```

### Field Reference

| Field | Required | Description |
|-------|----------|-------------|
| `path` | ✅ | Relative path on the agent. Must start with `/` |
| `methods` | ✅ | Allowed HTTP methods: `GET`, `POST`, `PUT`, `DELETE` |
| `description` | optional | Human-readable purpose shown in admin UI |

## Proxy URL Format

Once declared, the endpoint is reachable at:

```
https://<platform-domain>/api/agents/{agentName}{path}
```

Example: `https://brama.dev/api/agents/telegram-channel-agent/webhook/telegram`

The full proxy URL is shown in the admin panel under **Agent Settings → Public Endpoints**.

## Security

- The proxy route (`/api/agents/`) bypasses platform authentication (no edge-auth, no API token)
- Only declared endpoints are proxied — undeclared paths return `403 Forbidden`
- Agents are responsible for their own request authentication (e.g., Telegram webhook secret verification via `X-Telegram-Bot-Api-Secret-Token`)

## Forwarded Headers

The proxy forwards the following headers from the incoming request to the agent:

- `Content-Type`
- `Accept`
- `Accept-Language`
- `X-Telegram-Bot-Api-Secret-Token`
- `X-Forwarded-For` (client IP)
- `X-Forwarded-Host` (original host)

## Error Responses

| Condition | HTTP Status | Body |
|-----------|-------------|------|
| Agent not found | `404 Not Found` | `{"error": "Agent not found"}` |
| Agent is disabled | `503 Service Unavailable` | `{"error": "Agent is disabled"}` |
| Path not declared | `403 Forbidden` | `{"error": "Endpoint not exposed"}` |
| Method not allowed | `405 Method Not Allowed` | — |
| Agent unreachable | `502 Bad Gateway` | `{"error": "Agent unreachable"}` |
| Agent timed out | `504 Gateway Timeout` | `{"error": "Agent request timed out"}` |

## Timeout Configuration

The proxy uses a configurable timeout (default: 30 seconds):

```env
AGENT_PROXY_TIMEOUT=15
```

## Endpoint Sync

Public endpoints are synced from the agent manifest:

- **On discovery** (`POST /admin/agents/discover`): endpoints are synced immediately after manifest fetch
- **On health poll** (`app:agent-health-poll`): endpoints are refreshed on every successful health check

The sync uses a **full-replace strategy**: all existing rows for the agent are deleted and re-inserted from the manifest. This ensures the database always matches the manifest exactly.

When an agent is deleted from the registry, all its `agent_public_endpoints` rows are cascade-deleted.
