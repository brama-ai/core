# Change: Add Langfuse Observability and Admin Tools Navigation

## Why
The platform currently has partial invocation audit logs in `core`, but there is no centralized trace view for multi-hop agent flows. Operators need a dedicated observability UI and quick admin access to it. We also need immediate trace emission for the active OpenClaw -> core -> hello-agent path.

## What Changes
- Add self-hosted Langfuse services to the root Docker Compose stack
- Expose Langfuse UI through Traefik on a dedicated local entrypoint
- Add Langfuse tracing for OpenClaw-originated invocation flows in `core`
- Add Langfuse tracing for `hello-agent` A2A requests
- Propagate trace/correlation headers from `core` to downstream A2A calls
- Add Traefik forward-auth integration with JWT cookie login flow for protected tool entrypoints (`openclaw`, `langfuse`, `knowledge`, `news`, `hello`)
- Add an `Інструменти` section in admin sidebar with navigation links to observability tools
- Add E2E coverage for Langfuse navigation and edge-auth redirects
- Update local development docs with Langfuse setup and access details

## Impact
- Affected specs: `observability-integration`, `admin-tools-navigation`
- Affected code: `compose.yaml`, `docker/traefik/traefik.yml`, `apps/brama-core/`, `apps/hello-agent/`, `docs/local-dev.md`
