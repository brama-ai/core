# Dockerfile Ownership Rule

Each deployable project owns its `Dockerfile` at the project root. Docker Compose files remain
in the workspace as the assembly layer.

Ukrainian version: [`docs/guides/deployment/ua/dockerfile-ownership.md`](../ua/dockerfile-ownership.md)

## Rule

**Each deployable project SHALL own its `Dockerfile` at the project repository root.**

The workspace `docker/` directory contains only Compose files and workspace-level tooling images.
It does not own application Dockerfiles for deployable projects.

## Why

When the `Dockerfile` lives next to the code it builds, the project can be built independently
of the workspace. CI/CD pipelines, container registries, and deployment tools can reference a
single repository context without needing the full workspace tree.

## Compose Reference Pattern

Compose files reference project Dockerfiles via `build.context` and `build.dockerfile`:

```yaml
services:
  my-service:
    build:
      context: ../my-project        # path to the project directory
      dockerfile: Dockerfile        # project-owned Dockerfile at the project root
```

- `build.context` points to the project directory relative to the Compose file location
- `build.dockerfile` is always `Dockerfile` (the project-owned file at the project root)
- No Compose service SHALL use a `build.dockerfile` path that points into `docker/` for a
  deployable project image

## Current Project Layout

All deployable projects already conform to this rule:

| Project | Dockerfile location | Compose reference |
|---------|-------------------|-------------------|
| brama-core | `brama-core/Dockerfile` | `context: ../brama-core`, `dockerfile: Dockerfile` |
| hello-agent | `brama-agents/hello-agent/Dockerfile` | `context: ../brama-agents/hello-agent`, `dockerfile: Dockerfile` |
| knowledge-agent | `brama-agents/knowledge-agent/Dockerfile` | `context: ../brama-agents/knowledge-agent`, `dockerfile: Dockerfile` |
| news-maker-agent | `brama-agents/news-maker-agent/Dockerfile` | `context: ../brama-agents/news-maker-agent`, `dockerfile: Dockerfile` |
| wiki-agent | `brama-agents/wiki-agent/Dockerfile` | `context: ../brama-agents/wiki-agent`, `dockerfile: Dockerfile` |
| dev-reporter-agent | `brama-agents/dev-reporter-agent/Dockerfile` | `context: ../brama-agents/dev-reporter-agent`, `dockerfile: Dockerfile` |
| website | `brama-website/Dockerfile` | `context: ../brama-website`, `dockerfile: Dockerfile` |

## Exceptions

The following Dockerfiles are workspace-owned tooling images, not deployable projects. They are
exempt from the project-ownership rule:

| File | Purpose |
|------|---------|
| `.devcontainer/Dockerfile` | Developer tooling image for the devcontainer |
| `docker/slides/Dockerfile` | Presentation utility (Slidev) |
| `templates/agent/Dockerfile` | Agent scaffold template — not a live build |

## Adding a New Deployable Project

When adding a new deployable project:

1. Place the `Dockerfile` at the root of the project directory (e.g., `brama-agents/my-agent/Dockerfile`)
2. In the Compose fragment, reference it with:
   ```yaml
   build:
     context: ../brama-agents/my-agent
     dockerfile: Dockerfile
   ```
3. Do **not** place the `Dockerfile` under `docker/` or any workspace-level directory

See `templates/agent/compose.fragment.yaml` for a reference Compose fragment.
