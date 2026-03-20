## 1. Infrastructure
- [x] 1.1 Add Langfuse web/worker and required dependencies to root `compose.yaml`
- [x] 1.2 Expose Langfuse via Traefik entrypoint `:8086`
- [x] 1.3 Protect tools entrypoints via Traefik forward-auth middleware

## 2. Core Observability
- [x] 2.1 Add a Langfuse ingestion client in `apps/core`
- [x] 2.2 Emit OpenClaw invocation trace/span events in `/api/v1/agents/invoke`
- [x] 2.3 Emit outbound A2A call spans and propagate trace/correlation headers to downstream agents

## 3. Hello-Agent Observability
- [x] 3.1 Add a Langfuse ingestion client in `apps/hello-agent`
- [x] 3.2 Emit trace/span events for incoming A2A requests in `hello-agent`

## 4. Admin Navigation
- [x] 4.1 Add an `Інструменти` section to the admin sidebar
- [x] 4.2 Add a Langfuse navigation link/button from admin UI

## 5. Edge Auth + Access Control
- [x] 5.1 Implement `/edge/auth/login` and `/edge/auth/verify` in `core`
- [x] 5.2 Add JWT cookie issuance/validation for Traefik tool access
- [x] 5.3 Apply edge-auth to `openclaw`, `langfuse`, and all agent entrypoints

## 6. E2E Coverage
- [x] 6.1 Add E2E for Langfuse button navigation from admin dashboard
- [x] 6.2 Add E2E for anonymous redirects to edge login on protected tool URLs
- [x] 6.3 Add E2E for JWT cookie creation on edge login

## 7. Documentation
- [x] 7.1 Update `docs/local-dev.md` with Langfuse topology, auth, and access workflow

## 8. Quality Checks
- [x] 8.1 Run `openspec validate add-langfuse-observability --strict`
- [x] 8.2 Run static checks for changed PHP code paths
