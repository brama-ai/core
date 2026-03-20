# Design: Bootstrap Platform Foundation

## Context

The platform is transitioning from documentation-first to initial implementation. The first
concrete deliverable is a Symfony 7 application running on PHP 8.5 that can be verified
end-to-end via Playwright.

Current state:
- `apps/core/public/index.php` is a plain text stub
- `composer.json` pins `^8.3` but `openspec/project.md` mandates PHP 8.5
- `docker/core/Dockerfile` uses `php:8.3-apache`
- No framework, no tooling, no tests
- Traefik is already running and routes all requests to `core` on `http://localhost/` via the
  `web` entrypoint (port 80, `PathPrefix(/)` rule) — established in `add-local-dev-compose-topology`

## Goals / Non-Goals

Goals:
- Fix PHP version to 8.5 everywhere (Dockerfile, composer.json)
- Initialize Symfony 7 with Flex in `apps/core/`
- Configure Codeception, PHPStan (level 8), PHP CS Fixer
- Establish Postgres connection — no migrations or schema, connection-only
- Add `LiteLLM` as the local proxy for LLM traffic inspection and future provider abstraction
- Deliver `GET /health` as the first verifiable endpoint
- Add Playwright for E2E verification of HTTP endpoints
- Update agent-facing documentation so LLM usage is standardized across agents

Non-Goals:
- Database schema or migrations (separate proposal)
- Telegram adapter (separate proposal)
- Agent registry, event bus, command router (separate proposals)
- Web admin panel (explicitly excluded from MVP scope per `openspec/project.md`)
- Selecting or integrating a production LLM provider in this change

## Decisions

**PHP 8.5 + Symfony 7**
Fixed in `openspec/project.md` — no alternatives considered.

**Doctrine DBAL only (not ORM)**
Postgres connection without entity mapping. Minimal footprint for this phase; ORM and schema
belong to the data layer proposal.

**LiteLLM as the local LLM gateway**
The local stack includes a `LiteLLM` proxy on `http://localhost:4000`. In development, any
agent or orchestration layer that needs an LLM should call the proxy instead of calling provider
SDKs or provider endpoints directly. This creates one debuggable choke point for:
- request/response inspection through container logs
- consistent future model aliasing
- central provider switching without rewriting agent contracts

The first bootstrap uses `LiteLLM` as a debug and routing layer only. It does not commit the
project to any specific upstream provider yet.

**GET /health is stateless**
Returns `{"status": "ok", "service": "core-platform", "version": "0.1.0"}` without a Postgres
probe. A DB connectivity check can be added in a follow-up once the schema is established.
Stateless health check is safe to call at any time, even before DB migrations run.

**Playwright for E2E (alongside Codeception)**
Codeception covers unit and functional (in-process) tests. Playwright covers the full request
path from outside the stack: browser/HTTP client → Traefik → core container → Symfony kernel.
This validates that Traefik routing rules, Symfony front controller, and Docker Compose topology
all work end-to-end. E2E tests target `http://localhost/health` (through Traefik, not a direct
container port). Adds Node.js tooling to a PHP project — accepted cost, run as a separate CI job.

**Traefik REST API enabled locally (insecure mode)**
Traefik v3 already has `api: dashboard: false` in `traefik.yml`, which means the API is active
but not accessible without a router rule. Adding `insecure: true` exposes the API on the default
port `8080` without a router. This enables Playwright tests to verify:
- Traefik is up: `GET :8080/api/http/services` → `200`
- `core` container is registered: response contains `core@docker`
- `core` router is active: `GET :8080/api/http/routers` → router with `status: enabled`

`insecure: true` is local dev only; it is never used in staging or production environments.

**PHPStan level 8 from day one**
Strictest mode is easier to maintain than relaxing later. New codebase with no legacy debt makes
this the right time to enforce it.

## Risks / Trade-offs

- PHP 8.5 + Symfony 7 package compatibility: some Composer packages may lag PHP 8.5 support.
  Mitigation: verify compatibility before adding any dependency; use `minimum-stability: stable`.
- Playwright introduces Node.js into a PHP-first project. Mitigation: isolate in `tests/e2e/`,
  run as a separate optional CI step, document clearly in `LOCAL_DEV.md`.
- `LiteLLM` adds another moving part to the dev stack. Mitigation: keep it local-only for now,
  expose one conventional port (`4000`), and use it as an optional proxy until agents start
  sending real traffic through it.

## Open Questions

- None blocking. DB connectivity probe in `/health` deferred to next proposal.
