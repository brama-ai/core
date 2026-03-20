## Context

The current OpenClaw integration path is:

`OpenClaw -> core (/api/v1/agents/invoke) -> AgentInvokeBridge -> hello-agent (/api/v1/a2a)`

`core` persists invocation audit rows, but there is no centralized trace timeline for operators.

## Design Decisions

### 1. Self-hosted Langfuse in local compose

We run Langfuse v3 stack in the same Docker network for local development:

- `langfuse-web`
- `langfuse-worker`
- `langfuse-postgres`
- `langfuse-clickhouse`
- `langfuse-redis`
- `langfuse-minio`

UI is exposed through Traefik on `http://localhost:8086/`.

### 2. Lightweight ingestion client in PHP apps

Instead of introducing full OpenTelemetry SDK plumbing in this step, both `core` and `hello-agent` send Langfuse events directly to `POST /api/public/ingestion`.

Properties:

- non-blocking behavior for business flow (ingestion failures are swallowed and logged)
- minimal dependencies (native HTTP stream context)
- explicit event model (`trace-create`, `span-create`)

### 3. Trace propagation and correlation

`core` adds propagation on outbound A2A requests:

- `traceparent`
- `x-request-id`
- `x-agent-run-id`
- `x-a2a-hop`

`hello-agent` reuses `trace_id` and `request_id` from inbound request envelopes to keep trace continuity.

### 4. Admin UX

Admin sidebar gets a dedicated `Інструменти` section with quick links to:

- OpenClaw UI
- Langfuse UI

This keeps operational tools discoverable from one place.

### 5. Edge access control via Traefik forward-auth

All external tools entrypoints are protected behind Traefik `forwardAuth`:

- `openclaw` (`:8082`)
- `knowledge` (`:8083`)
- `news` (`:8084`)
- `hello` (`:8085`)
- `langfuse` (`:8086`)

`core` provides:

- `GET/POST /edge/auth/login` (credential login + JWT cookie issue)
- `GET /edge/auth/verify` (JWT validation endpoint for Traefik)

When access is unauthorized, Traefik receives a redirect response to `/edge/auth/login?rd=<requested-url>`.

## Tradeoffs

- This step does not instrument OpenClaw internals inside the upstream image; traces start from the OpenClaw ingress handled by `core`.
- Direct ingestion is simpler now, but later can be replaced by OTEL SDK/exporter when multi-agent instrumentation expands.
