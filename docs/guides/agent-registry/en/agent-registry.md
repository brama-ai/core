# Admin Agent Registry

The Admin Agent Registry is the platform's central catalog for managing AI agents. It provides a web UI for discovering, installing, configuring, enabling, disabling, and monitoring agents registered on the platform.

## Data Model

Agents are stored in the `agent_registry` table with the following key columns:

| Column | Type | Description |
|--------|------|-------------|
| `name` | `varchar` | Unique agent identifier (slug, e.g. `hello-agent`) |
| `version` | `varchar` | Semantic version from the Agent Card |
| `manifest` | `jsonb` | Full Agent Card JSON (name, version, description, skills, etc.) |
| `config` | `jsonb` | Admin-editable config (description, system_prompt) |
| `enabled` | `boolean` | Whether the agent is active for event/command routing |
| `installed_at` | `timestamp` | Set when agent is provisioned; NULL = marketplace state |
| `health_status` | `varchar` | `healthy`, `degraded`, `unavailable`, `error`, `unknown` |
| `violations` | `jsonb` | Convention violation messages from discovery |
| `tenant_id` | `uuid` | Tenant isolation |

Audit events are stored in `agent_registry_audit` with columns: `agent_name`, `action`, `actor`, `payload`, `tenant_id`, `created_at`.

## Agent Lifecycle

```
[Marketplace]  →  install  →  [Installed/Disabled]  →  enable  →  [Installed/Enabled]
                                       ↑                                    ↓
                                    delete                               disable
                                       ↑                                    ↓
                               [Marketplace]          ←←←←←←←←←←←←←←←←←←←←
```

### States

| State | `installed_at` | `enabled` | Description |
|-------|---------------|-----------|-------------|
| Marketplace | `NULL` | `false` | Discovered but not provisioned |
| Installed/Disabled | `IS NOT NULL` | `false` | Provisioned, not routing events |
| Installed/Enabled | `IS NOT NULL` | `true` | Active, routing events and commands |

### Lifecycle Actions

- **Register**: Agent self-registers via `POST /api/v1/internal/agents/register` with its Agent Card. Requires `X-Platform-Internal-Token` header.
- **Install**: Admin provisions storage (Postgres/Redis/OpenSearch per manifest) and sets `installed_at`. Audit action: `installed`.
- **Enable**: Admin activates the agent for event routing. Audit action: `enabled`.
- **Disable**: Admin deactivates the agent. Audit action: `disabled`.
- **Delete (Uninstall)**: Admin deprovisions storage and clears `installed_at`. Agent returns to marketplace. Audit action: `uninstalled`.

## Admin UI

### Agent List Page (`/admin/agents`)

The agent list page displays all registered agents in two tabs:

- **Installed** — agents with `installed_at IS NOT NULL`
- **Marketplace** — agents with `installed_at IS NULL`

Each row shows: name, version, description, status badge, health badge, last updated, and action buttons.

**Actions available per tab:**

| Tab | Actions |
|-----|---------|
| Installed (enabled) | Disable, Settings |
| Installed (disabled) | Enable, Delete |
| Marketplace | Install |

The **Discover Agents** button triggers pull-based discovery (Traefik or Kubernetes) and refreshes the registry.

### Agent Settings Page (`/admin/agents/{name}/settings`)

The settings page provides:

1. **Configuration form** — editable `description` and `system_prompt` fields, saved via `PUT /api/v1/internal/agents/{name}/config`
2. **Agent Card** — read-only display of version, description, commands, events, skills, capabilities
3. **Admin iframe** — embedded agent admin panel (if the agent's manifest includes `admin_url`)

### Violations Modal

Agents with convention violations show a **Degraded** health badge. Clicking the badge opens a modal listing all violation messages (e.g., "Missing /health endpoint", "Missing Docker label").

## API Endpoints

All endpoints require `ROLE_ADMIN` (session auth) except registration which uses `X-Platform-Internal-Token`.

| Method | Path | Description |
|--------|------|-------------|
| `POST` | `/api/v1/internal/agents/register` | Register/update agent (token auth) |
| `GET` | `/api/v1/internal/agents` | List all agents (admin auth) |
| `POST` | `/api/v1/internal/agents/{name}/install` | Install agent |
| `POST` | `/api/v1/internal/agents/{name}/enable` | Enable agent |
| `POST` | `/api/v1/internal/agents/{name}/disable` | Disable agent |
| `PUT` | `/api/v1/internal/agents/{name}/config` | Save agent config |
| `DELETE` | `/api/v1/internal/agents/{name}` | Uninstall agent |

## Health Polling

The platform runs a periodic health poller (`AgentHealthPollerCommand`) that:

1. Fetches `GET /health` from each registered agent
2. Updates `health_status` in the registry
3. Runs convention verification (checks Docker labels, manifest fields, endpoint availability)
4. Stores violations in the `violations` column
5. Cleans up stale marketplace agents (never installed, `health_check_failures >= 5`)

An inline health probe also runs at registration time if the manifest includes a `health_url` field.

## Discovery

Agents are discovered via pull-based providers:

- **Traefik** — queries Traefik API for services with the `ai.platform.agent=true` Docker label
- **Kubernetes** — queries Kubernetes API for pods with the same label

Discovery fetches the Agent Card from `http://{hostname}/api/v1/manifest` and upserts the agent into the registry.

## i18n

All admin UI strings use Symfony translation keys via the `|trans` Twig filter. Translation files are located at:

- `src/translations/messages.uk.yaml` — Ukrainian (canonical)
- `src/translations/messages.en.yaml` — English

Agent registry keys are prefixed with `agents.` (list page) and `agent_settings.` (settings page).
