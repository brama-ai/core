# Migration Playbook — Moving an Agent to an External Repository

This playbook describes how to migrate an agent from `apps/<name>/` in the platform monorepo
to its own external repository under the `projects/<name>/` workspace convention.

---

## When to Migrate

Migrate an agent to an external repository when:

- The agent has its own release cadence independent of the platform
- The agent is maintained by a different team or contributor
- The agent's stack or tooling differs significantly from the platform core

Do **not** migrate if:

- The agent is a platform-owned reference implementation (e.g., `hello-agent` stays in-repo as
  the canonical example)
- The agent has unresolved coupling to platform internals that must be decoupled first

---

## Pre-Migration Checklist

Before starting, verify the agent satisfies the external agent contract:

- [ ] `GET /health` returns `{"status": "ok"}` with HTTP 200
- [ ] `GET /api/v1/manifest` returns a valid Agent Card with `name`, `version`, and `url`
- [ ] `POST /api/v1/a2a` handles all declared skills (if `skills` is non-empty)
- [ ] Service name ends with `-agent`
- [ ] Docker label `ai.platform.agent=true` is present
- [ ] No hardcoded references to other agent service names (use `PLATFORM_CORE_URL` instead)
- [ ] All environment variables are documented in the agent's README or `.env.example`
- [ ] `make conventions-test AGENT_URL=http://localhost:<port>` passes

---

## Step 1: Create the External Repository

Create a new Git repository for the agent. The repository layout MUST include:

```
<agent-repo>/
  compose.fragment.yaml     # Compose service definition
  Dockerfile                # Docker build context (self-contained)
  src/                      # Application source code
  README.md                 # Setup and runtime instructions
  .env.example              # Required environment variables
```

### Dockerfile

The Dockerfile MUST be self-contained — it should not rely on the platform repository's
directory structure. Copy source from the repository root, not from `apps/<name>/`:

```dockerfile
# Before (in-repo, platform-owned build context):
COPY apps/hello-agent/ /var/www/html/

# After (external repo, agent-owned build context):
COPY src/ /var/www/html/
```

### compose.fragment.yaml

The compose fragment MUST:

- Use the same service name as the in-repo version (no rename during migration)
- Include `ai.platform.agent=true`
- Attach to the `dev-edge` network
- Reference the agent's own Dockerfile (not the platform's `docker/<name>/Dockerfile`)

```yaml
services:
  hello-agent:
    build:
      context: .
      dockerfile: Dockerfile
    labels:
      - ai.platform.agent=true
    environment:
      PLATFORM_CORE_URL: http://core
      APP_INTERNAL_TOKEN: ${APP_INTERNAL_TOKEN:-dev-internal-token}
    networks:
      - dev-edge
```

---

## Step 2: Verify the External Repository Locally

```bash
# Clone the new repository into projects/
git clone https://github.com/your-org/hello-agent.git projects/hello-agent/src

# Copy the compose fragment into the workspace directory
cp projects/hello-agent/src/compose.fragment.yaml projects/hello-agent/compose.fragment.yaml

# Start the agent from the external workspace
make external-agent-up name=hello-agent

# Verify health
curl -s http://localhost:<port>/health

# Verify manifest
curl -s http://localhost:<port>/api/v1/manifest | jq .

# Run convention tests
make conventions-test AGENT_URL=http://localhost:<port>
```

---

## Step 3: Add to compose.external-agents.yaml

```yaml
include:
  - path: projects/hello-agent/compose.fragment.yaml
    required: false
```

---

## Step 4: Remove from In-Repo Compose

Once the external workflow is verified:

1. Remove the agent's include from `compose.agent-<name>.yaml` (or deprecate the file)
2. Keep `apps/<name>/` as a read-only archive until all operators have migrated
3. Add a deprecation notice to `apps/<name>/README.md`

---

## Step 5: Update Platform Documentation

- Update `docs/index.md` — change the agent's source path from `apps/<name>/` to
  `projects/<name>/`
- Update `docs/agents/en/<name>.md` — note the external repository URL
- Update `docs/agents/ua/<name>.md` — same

---

## Compatibility Rules During Transition

The following MUST remain stable across the migration:

| Contract point | Rule |
|----------------|------|
| Service name | MUST NOT change (e.g., `hello-agent` stays `hello-agent`) |
| Manifest `name` field | MUST match the service name |
| Manifest `version` | MUST follow semver; increment PATCH for migration-only changes |
| Health endpoint path | MUST remain `/health` |
| Manifest endpoint path | MUST remain `/api/v1/manifest` |
| A2A endpoint path | MUST remain `/api/v1/a2a` (or the path declared in `url`) |
| Admin URL shape | MUST remain `/admin/<name>` if previously declared |
| Docker label | MUST include `ai.platform.agent=true` |
| Network | MUST attach to `dev-edge` |

**No mixed service names**: do not run both `compose.agent-hello.yaml` and
`projects/hello-agent/compose.fragment.yaml` at the same time. Stop the in-repo version
before starting the external version.

---

## Rollback

If the external workflow fails:

```bash
# Stop the external agent
make external-agent-down name=hello-agent

# Remove the include from compose.external-agents.yaml

# Restart the in-repo agent
make agent-up name=hello-agent
```

---

## Related

- [External Agent Workspace](external-agent-workspace.md)
- [Operator Onboarding Guide](operator-onboarding.md)
- [Pilot Agent Selection](pilot-agent-selection.md)
- [Agent Platform Conventions](../../../agent-requirements/conventions.md)
