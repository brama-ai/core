# External Agent Onboarding Guide

This guide explains how to connect an externally maintained agent repository to the AI Community
Platform workspace without modifying the platform's core compose files.

## Overview

The platform supports two agent source origins:

| Origin | Location | Use case |
|--------|----------|----------|
| **In-repo** | `apps/<agent-name>/` | Reference agents bundled with the platform |
| **External** | `projects/<agent-name>/` | Independently maintained agent repositories |

Both origins use the same runtime contract: the same Docker labels, manifest endpoint, health
endpoint, and A2A interface. Source origin does not affect discovery or lifecycle management.

## Workspace Layout

```
ai-community-platform/          ← platform repository (git clone)
  compose.yaml                  ← shared infrastructure
  compose.core.yaml             ← core platform service
  compose.agent-*.yaml          ← bundled in-repo agent fragments
  compose.fragments/            ← external agent fragments (gitignored, operator-local)
    my-agent.yaml               ← copied from projects/my-agent/compose.fragment.yaml
  projects/                     ← external agent checkouts (gitignored, operator-local)
    my-agent/                   ← git clone of the agent repository
      Dockerfile
      compose.fragment.yaml     ← agent-provided compose fragment template
      .env.local                ← agent-specific secrets (never committed)
      ...
```

## Step-by-Step Onboarding

### 1. Clone the Agent Repository

```bash
# Option A: use the Makefile helper (recommended)
make external-agent-clone repo=https://github.com/your-org/my-agent name=my-agent

# Option B: manual clone
mkdir -p projects
git clone https://github.com/your-org/my-agent projects/my-agent
cp projects/my-agent/compose.fragment.yaml compose.fragments/my-agent.yaml
```

The `external-agent-clone` target:
- Clones the repository into `projects/<name>/`
- Copies `compose.fragment.yaml` to `compose.fragments/<name>.yaml` if it exists
- Prints next steps

### 2. Review the Compose Fragment

Open `compose.fragments/hello-agent.yaml` and verify:

- Service name ends with `-agent` (e.g. `hello-agent`)
- Label `com.a2a.agent=true` is present
- Network is `a2a-network`
- Healthcheck is configured
- It uses the GHCR Image-First Workflow (see below)

#### GHCR Image-First Workflow

We strongly recommend the **GHCR Image-First Workflow** for external agents. Instead of building the agent from source on every deployment, the platform pulls a pre-built image from the GitHub Container Registry (GHCR), using local build only as a fallback for development.

**Example `compose.fragment.yaml` (from hello-agent):**
```yaml
services:
  hello-agent:
    image: ghcr.io/nmdimas/a2a-hello-agent:main
    build:
      context: ./projects/hello-agent
...
```
- **When to Pull:** In production or testing, if the image exists on GHCR, Docker will pull and use it automatically. It's much faster.
- **When to Build:** If you are actively developing the agent, you can run `docker compose build hello-agent` to force the `build:` section to execute from local source.

### 3. Configure Agent Secrets

```bash
# Create agent-local env file (gitignored)
cp projects/my-agent/.env.local.example projects/my-agent/.env.local
nano projects/my-agent/.env.local
```

The compose fragment references this file via:
```yaml
env_file:
  - path: ./projects/my-agent/.env.local
    required: false
```

### 4. Start the Agent

```bash
make external-agent-up name=hello-agent
```

This builds the agent image from `projects/my-agent/` and starts the service in the platform
network. No changes to platform base files are required.

### 5. Verify Health and Discovery

```bash
# Check the agent is running
docker compose logs -f my-agent

# Check health endpoint
curl -s http://localhost:<port>/health

# Trigger platform discovery
make agent-discover
```

After discovery, the agent appears in the platform admin panel under **Agents → Marketplace**.

### 6. Install and Enable in Admin

1. Open the platform admin panel
2. Navigate to **Agents → Marketplace**
3. Click **Install** on the agent card (provisions storage and runs migrations)
4. Click **Enable** to activate the agent for traffic

---

## Upgrading an External Agent

```bash
# Pull the latest code
git -C projects/my-agent pull

# Rebuild and restart
make external-agent-up name=my-agent

# If the compose fragment changed, update it
cp projects/my-agent/compose.fragment.yaml compose.fragments/my-agent.yaml
make external-agent-up name=my-agent

# Run any new migrations (if the agent uses startup migrations, restart is enough)
# For manual migration:
docker compose exec my-agent <migration-command>

# Verify compatibility
make agent-discover
```

If the upgraded agent no longer satisfies platform conventions, the discovery cycle will surface
the violation in the admin panel. See the **Rollback** section below.

### Rollback

```bash
# Stop the agent
make external-agent-down name=my-agent

# Revert to a known-good commit
git -C projects/my-agent checkout <previous-tag>

# Restart
make external-agent-up name=my-agent
```

---

## Detaching an External Agent

```bash
# 1. Stop the agent service
make external-agent-down name=my-agent

# 2. Remove the compose fragment
rm compose.fragments/my-agent.yaml

# 3. (Optional) Remove the checkout
rm -rf projects/my-agent

# 4. (Optional) Deprovision in admin panel
#    Admin → Agents → Installed → Delete
#    This removes the agent's database, Redis keys, and OpenSearch indices.
```

---

## Listing External Agents

```bash
make external-agent-list
```

Output:
```
  External agent compose fragments:
  ─────────────────────────────────
  my-agent                       projects/my-agent
  another-agent                  (no checkout)
```

---

## Compose Fragment Contract

Every external agent compose fragment MUST satisfy the following:

| Requirement | Value |
|-------------|-------|
| Service name | Ends with `-agent` (e.g. `my-agent`) |
| Label | `ai.platform.agent=true` |
| Network | `dev-edge` |
| Healthcheck | `GET /health` returns `{"status":"ok"}` |
| Manifest | `GET /api/v1/manifest` returns valid Agent Card JSON |
| A2A endpoint | `POST /api/v1/a2a` (required if skills declared) |
| Inter-agent calls | Via `PLATFORM_CORE_URL/api/v1/a2a/send-message` only |

See `docs/agent-requirements/conventions.md` for the full contract.

---

## Environment and Secrets

| File | Location | Purpose |
|------|----------|---------|
| Platform secrets | `.env.local` (repo root) | LLM keys, Telegram token |
| Agent secrets | `projects/<name>/.env.local` | Agent-specific API keys, DB passwords |
| Compose fragment | `compose.fragments/<name>.yaml` | Runtime service definition |

Neither `projects/` nor `compose.fragments/*.yaml` are committed to the platform repository.
They are operator-local and gitignored.

---

## CI Expectations

- **Agent repository**: owns its own tests, linting, and build checks
- **Platform repository**: owns compatibility checks (`make conventions-test`) and E2E tests
- External agents are not included in the platform CI by default
- To run convention checks against an external agent:
  ```bash
  AGENT_URL=http://localhost:<port> make conventions-test
  ```
