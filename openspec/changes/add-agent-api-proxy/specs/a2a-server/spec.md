# a2a-server Specification (additions)

### Requirement: Agent API Proxy Controller

The platform SHALL expose a catch-all route at `/api/agents/{agentName}/{path}` that proxies HTTP requests to the matching agent's internal service endpoint.

Route: `GET|POST|PUT|DELETE /api/agents/{agentName}/{path}`
Controller: `App\Controller\Api\AgentProxyController`
Route name: `api_agent_proxy`

#### Scenario: Proxy request to agent with matching public endpoint
- **GIVEN** agent `telegram-channel-agent` is registered, enabled, and healthy
- **AND** it has a public endpoint `POST /webhook/telegram`
- **WHEN** a request arrives at `POST /api/agents/telegram-channel-agent/webhook/telegram`
- **THEN** Core forwards the request to `POST http://telegram-channel-agent:80/webhook/telegram`
- **AND** returns the agent's response (status code, headers, body) to the caller

#### Scenario: Proxy preserves request body and headers
- **WHEN** a request is proxied to an agent
- **THEN** the original request body is forwarded as-is
- **AND** the following headers are forwarded: `Content-Type`, `X-Telegram-Bot-Api-Secret-Token`, `Accept`, `Accept-Language`
- **AND** `X-Forwarded-For` and `X-Forwarded-Host` headers are added

#### Scenario: Agent not found
- **WHEN** a request arrives at `/api/agents/{agentName}/{path}` and `agentName` does not match any registered agent
- **THEN** Core returns `404 Not Found` with body `{"error": "Agent not found"}`

#### Scenario: Agent is disabled
- **WHEN** the matched agent exists but is disabled
- **THEN** Core returns `503 Service Unavailable` with body `{"error": "Agent is disabled"}`

#### Scenario: Endpoint not declared as public
- **WHEN** the matched agent exists and is enabled
- **AND** the requested path is NOT in the agent's `public_endpoints`
- **THEN** Core returns `403 Forbidden` with body `{"error": "Endpoint not exposed"}`

#### Scenario: HTTP method not allowed
- **WHEN** the requested path matches a public endpoint
- **AND** the HTTP method is not in the endpoint's `methods` array
- **THEN** Core returns `405 Method Not Allowed`

#### Scenario: Agent is unreachable (proxy error)
- **WHEN** the request is forwarded to the agent but the agent does not respond within 30 seconds
- **THEN** Core returns `502 Bad Gateway` with body `{"error": "Agent unreachable"}`

### Requirement: Proxy Security

The proxy SHALL NOT require platform authentication (no edge-auth, no API token) for proxied requests. This is necessary because external services (Telegram, OAuth providers) cannot authenticate with the platform. Agents are responsible for their own request authentication (e.g., Telegram webhook secret verification).

#### Scenario: Proxy route bypasses edge-auth
- **WHEN** Traefik or Symfony security processes a request to `/api/agents/{agentName}/{path}`
- **THEN** the request is allowed without platform authentication
- **AND** the full original request (including any auth headers from external services) is forwarded to the agent

### Requirement: Proxy Timeout

The proxy SHALL use a configurable timeout (default: 30 seconds) for upstream agent requests. The timeout SHALL be configurable via the `AGENT_PROXY_TIMEOUT` environment variable (integer, seconds).

### Requirement: Admin Visibility

The admin agents page SHALL display the list of public endpoints for each agent that has them.

#### Scenario: Agent detail shows public endpoints
- **WHEN** viewing an agent in the admin panel that has public_endpoints
- **THEN** a "Public Endpoints" section displays each endpoint's method, path, and the full proxy URL (`https://brama.dev/api/agents/{name}/{path}`)
