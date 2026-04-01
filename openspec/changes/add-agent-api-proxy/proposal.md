# Change: Add Agent API Proxy

## Why

Agents expose public HTTP endpoints (e.g., Telegram webhook callbacks, OAuth callbacks) that need to be reachable from the internet. Currently, each agent would need its own subdomain and Cloudflare Tunnel route, which doesn't scale and complicates infrastructure. A core reverse proxy lets all agents share a single domain with auto-discovered routing.

## What Changes

- **Agent Card schema**: New optional `public_endpoints` array in agent manifests
- **Database**: New `agent_public_endpoints` table storing declared endpoints per agent
- **AgentCardFetcher**: Parses and syncs `public_endpoints` from manifests during health polls
- **AgentProxyController** (new): Catch-all route at `/api/agents/{agentName}/{path}` that validates and proxies requests to agents
- **Symfony security**: `/api/agents/` prefix bypasses platform authentication (agents handle their own auth)
- **Admin UI**: "Public Endpoints" section on agent detail page showing proxy URLs
- **Agent manifests**: telegram-channel-agent adds `public_endpoints` declaration

## Impact

- Affected specs: `a2a-server`, `agent-registry`
- Affected code:
  - `src/Controller/Api/AgentProxyController.php` (new)
  - `src/Entity/AgentPublicEndpoint.php` (new)
  - `src/Repository/AgentPublicEndpointRepository.php` (new)
  - `src/Service/AgentCardFetcher.php` (modified)
  - `config/agent-card.schema.json` (modified)
  - `config/packages/security.yaml` (modified)
  - `templates/admin/agents/` (modified)
  - Doctrine migration for `agent_public_endpoints` table

## Out of Scope

- Authentication/rate-limiting of proxied requests (agents handle their own auth, e.g., Telegram webhook secret)
- WebSocket proxying (HTTP only for now)
- Request/response transformation
