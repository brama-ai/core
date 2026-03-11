# hello-agent — External Agent Workspace

This directory is the **pilot external agent checkout** for the AI Community Platform.

It demonstrates the `projects/<agent-name>/` workspace convention defined in
[`docs/guides/external-agents/en/external-agent-workspace.md`](../../docs/guides/external-agents/en/external-agent-workspace.md).

## Layout

```
projects/hello-agent/
  compose.fragment.yaml   # Compose service definition for this agent
  src/                    # Agent source code (symlink or checkout)
  README.md               # This file
```

## Quick Start

```bash
# From the platform root
make external-agent-up name=hello-agent
```

## Runtime Contract

| Contract point     | Value                          |
|--------------------|--------------------------------|
| Service name       | `hello-agent`                  |
| Manifest endpoint  | `GET /api/v1/manifest`         |
| Health endpoint    | `GET /health`                  |
| A2A endpoint       | `POST /api/v1/a2a`             |
| Admin URL          | *(none — no admin UI)*         |
| Docker label       | `ai.platform.agent=true`       |
| Network            | `dev-edge`                     |

## Source

The `src/` directory should contain the agent source code. For the in-repo pilot, it is a
symlink to `../../apps/hello-agent`. For a real external repository, the operator clones the
agent repository here:

```bash
git clone https://github.com/your-org/hello-agent.git projects/hello-agent/src
```

See the [migration playbook](../../docs/guides/external-agents/en/migration-playbook.md) for
step-by-step instructions.
