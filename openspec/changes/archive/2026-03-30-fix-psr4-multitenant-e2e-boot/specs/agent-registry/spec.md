## ADDED Requirements
### Requirement: Internal Agent Registration Tenant Fallback

The internal agent registration API (`POST /api/v1/internal/agents/register`) SHALL fall back to the default tenant when no tenant context is available in the request.

This ensures that headless internal API calls (authenticated via `X-Platform-Internal-Token` without a session) can register agents without requiring explicit tenant context.

#### Scenario: Internal registration without session uses default tenant

- **WHEN** an agent sends a `POST /api/v1/internal/agents/register` request with a valid internal token
- **AND** the request does not carry a session or tenant context
- **THEN** the system resolves the default tenant (slug `default`) and registers the agent under that tenant

#### Scenario: Internal registration with existing tenant context preserves it

- **WHEN** an authenticated admin user triggers agent registration through the admin UI
- **AND** a tenant context is already set via session
- **THEN** the system uses the existing tenant context and does not override it with the default tenant
