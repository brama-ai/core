# External Agent Workspace

## Overview

The AI Community Platform supports two source origins for agents:

| Origin | Location | Use case |
|--------|----------|----------|
| **In-repo** | `apps/<agent-name>/` | Platform-owned reference agents |
| **External** | `projects/<agent-name>/` | Independently maintained agents |

Both origins use the same runtime contract. Core discovers and manages them identically once
the container is running.

---

## Workspace Convention

External agents are checked out into `projects/<agent-name>/` inside the platform workspace:

```
ai-community-platform/
  compose.yaml                        # Platform base services
  compose.core.yaml                   # Core service
  compose.fragments/                  # Operator-local external agent fragments
    my-agent.yaml                     # Copied from projects/my-agent/compose.fragment.yaml
  projects/
    my-agent/                         # External agent repository checkout
      Dockerfile
      compose.fragment.yaml           # Agent-provided compose service definition
      .env.local                      # Operator-local secrets
      README.md
```

### Naming Rules

| Item | Convention | Example |
|------|-----------|---------|
| Directory | `projects/<agent-name>/` | `projects/hello-agent/` |
| Compose fragment template | `compose.fragment.yaml` | inside the agent checkout |
| Enabled fragment | `compose.fragments/<agent-name>.yaml` | copied into the platform workspace |
| Service name | Must end with `-agent` | `hello-agent` |
| Docker label | `ai.platform.agent=true` | required for discovery |

---

## Required Files in an External Agent Repository

Every external agent repository MUST provide:

```
<agent-repo>/
  compose.fragment.yaml     # Compose service definition
  Dockerfile                # Docker build context
  ...                       # Application source owned by the agent repo
  README.md                 # Setup instructions
```

### compose.fragment.yaml

The compose fragment defines the agent service. It MUST:

- Use a service name ending in `-agent`
- Include the label `ai.platform.agent=true`
- Attach to the `dev-edge` network
- Implement the health, manifest, and A2A endpoints (see [conventions](../../../agent-requirements/conventions.md))

Minimal example:

```yaml
services:
  my-agent:
    build:
      context: ./projects/my-agent
      dockerfile: Dockerfile
    labels:
      - ai.platform.agent=true
    environment:
      PLATFORM_CORE_URL: http://core
      APP_INTERNAL_TOKEN: ${APP_INTERNAL_TOKEN:-dev-internal-token}
    networks:
      - dev-edge
```

> **Note**: The `networks` block references the platform network. The network is defined in
> `compose.yaml` and is available to all fragments loaded from `compose.fragments/*.yaml`
> by the platform `Makefile`.

---

## Runtime Contract

External agents MUST implement the same endpoints as in-repo agents:

| Endpoint | Method | Required | Description |
|----------|--------|----------|-------------|
| `/health` | GET | Yes | Returns `{"status": "ok"}` |
| `/api/v1/manifest` | GET | Yes | Returns the Agent Card JSON |
| `/api/v1/a2a` | POST | If skills declared | Handles A2A skill requests |

See [Agent Platform Conventions](../../../agent-requirements/conventions.md) for the full contract.

---

## Discovery

Core discovers external agents using the same Traefik-based mechanism as in-repo agents:

1. The agent container starts and joins the `dev-edge` network
2. Traefik detects the container via the Docker socket
3. Core queries Traefik for services with the `ai.platform.agent=true` label
4. Core fetches `/api/v1/manifest` from each discovered service
5. The agent appears in the admin registry

No special registration is needed for external agents. The source origin is invisible to core
after the container is running.

---

## CI Expectations

| Concern | Owner |
|---------|-------|
| Unit and functional tests | Agent repository |
| Dockerfile build | Agent repository |
| Platform convention compliance | Platform core repo (`make conventions-test`) |
| Compose fragment validity | Platform core repo |

The platform CI does not run agent-specific tests. Each agent repository maintains its own
test suite and CI pipeline.

---

## Related

- [Operator Onboarding Guide](operator-onboarding.md)
- [Migration Playbook](migration-playbook.md)
- [Agent Platform Conventions](../../../agent-requirements/conventions.md)
- [Pilot Agent Selection](pilot-agent-selection.md)
