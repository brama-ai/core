## ADDED Requirements

### Requirement: Agent Public Endpoints in Manifest

The Agent Card schema SHALL support an optional `public_endpoints` array. Each entry declares an HTTP endpoint that the agent wants exposed through the platform's reverse proxy.

#### Schema

```json
{
  "public_endpoints": [
    {
      "path": "/webhook/telegram",
      "methods": ["POST"],
      "description": "Telegram Bot API webhook receiver"
    }
  ]
}
```

- `path` (required): Relative path on the agent (e.g., `/webhook/telegram`). Must start with `/`.
- `methods` (required): Array of HTTP methods (`GET`, `POST`, `PUT`, `DELETE`).
- `description` (optional): Human-readable purpose of the endpoint.

#### Scenario: Agent manifest includes public endpoints
- **WHEN** an agent returns a manifest with a `public_endpoints` array
- **THEN** the platform stores each endpoint in the `agent_public_endpoints` table linked to the agent's registry entry

#### Scenario: Agent manifest without public endpoints
- **WHEN** an agent returns a manifest without `public_endpoints`
- **THEN** no public endpoint records are created for that agent

#### Scenario: Agent updates its public endpoints
- **WHEN** an agent's manifest changes its `public_endpoints` array (detected during health poll)
- **THEN** the platform replaces the stored endpoints with the new set (full sync, not merge)

### Requirement: Agent Public Endpoints Table

The platform SHALL store public endpoint declarations in an `agent_public_endpoints` table.

| Column | Type | Description |
|--------|------|-------------|
| `id` | SERIAL PK | Auto-increment |
| `agent_id` | UUID FK -> agent_registry | Owning agent |
| `path` | VARCHAR(255) | Relative path on agent |
| `methods` | JSON | Allowed HTTP methods |
| `description` | TEXT NULL | Human-readable description |
| `created_at` | TIMESTAMP | When discovered |
| `updated_at` | TIMESTAMP | Last sync |

Unique constraint: `(agent_id, path)`.

#### Scenario: Endpoints cleaned up when agent is deleted
- **WHEN** an agent is removed from the registry
- **THEN** all its `agent_public_endpoints` rows are cascade-deleted

#### Scenario: Endpoint lookup by agent and path
- **WHEN** the proxy controller receives a request for agent `{name}` and path `{path}`
- **THEN** the platform queries `agent_public_endpoints` joined with `agent_registry` to find a matching row
- **AND** returns the allowed methods for validation
