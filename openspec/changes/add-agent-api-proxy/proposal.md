# Agent API Proxy

## Problem

Agents expose public API endpoints (e.g., Telegram webhook callbacks, OAuth callbacks, health checks) that need to be reachable from the internet. Currently, each agent would need its own subdomain and Cloudflare Tunnel route, which doesn't scale and complicates infrastructure.

## Solution

Core platform acts as a **reverse proxy** for agent public endpoints. Agents declare their public endpoints in their manifest (`GET /api/v1/manifest`). Core discovers these during agent registration/health polling, stores them in the database, and proxies matching incoming requests to the appropriate agent service.

**Traffic flow:**
```
Internet → Cloudflare Tunnel → brama.dev → Core (Traefik ingress)
  → /api/agents/{agent-name}/{path} → Core proxy → Agent service
```

## Rationale

- **Single domain**: All agents reachable via `brama.dev` — no extra DNS/tunnel config per agent
- **Security**: Core validates agent existence and enabled status before proxying
- **Discovery**: Public endpoints auto-discovered from manifests — no manual routing config
- **Consistency**: Follows existing A2A Gateway pattern (Core already proxies A2A messages)

## Impact

- **agent-registry**: Stores public endpoint paths per agent
- **a2a-server**: New proxy controller alongside existing A2A gateway
- **Agent manifests**: New optional `public_endpoints` section
- **Database**: New `agent_public_endpoints` table (or column on agent_registry)

## Out of Scope

- Authentication/rate-limiting of proxied requests (agents handle their own auth, e.g., Telegram webhook secret)
- WebSocket proxying (HTTP only for now)
- Request/response transformation
