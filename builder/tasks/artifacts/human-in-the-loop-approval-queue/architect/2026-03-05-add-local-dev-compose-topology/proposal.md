# Change: Add Local Docker Compose Development Topology

## Why

The project needs a small, reproducible local runtime layout for validating platform boundaries before full implementation starts. A stub-first Docker Compose setup lets the team verify routing, service isolation, and the `core-platform` / `OpenClaw` split without committing to full application code yet.

## What Changes

- Define a local `docker compose` topology with `Traefik` as the public routing layer.
- Reserve three routed surfaces for local development: `core`, `admin-stub`, and `openclaw-stub`.
- Add baseline local infrastructure services used by the platform: `Postgres`, `Redis`, `OpenSearch`, and `RabbitMQ`.
- Keep `core` aligned with the planned `PHP + Symfony 7 + Composer + Neuron AI` direction while starting with a minimal hello world stub.
- Preserve the documented rule that `OpenClaw` is a runtime candidate for `core-agent`, not the owner of the platform boundary.
- Treat any `admin` surface in this phase as a technical placeholder for routing and isolation checks, not as an approved MVP web admin panel.

## Impact

- Affected specs: `local-dev-runtime`
- Affected code: future `compose.yaml`, `docker/`, `apps/` or `services/` bootstrap directories, local runtime docs
