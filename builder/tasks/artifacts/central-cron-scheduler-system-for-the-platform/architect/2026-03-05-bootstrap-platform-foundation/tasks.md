## 1. PHP & Framework Setup

- [x] 1.1 Update `docker/core/Dockerfile` base image to `php:8.5-apache`
- [x] 1.2 Update `apps/core/composer.json` PHP requirement to `^8.5`
- [x] 1.3 Initialize Symfony 7 skeleton in `apps/core/` via Composer Flex
- [x] 1.4 Remove `apps/core/public/index.php` stub; confirm Symfony front controller is in place
- [x] 1.5 Verify `docker compose up core` boots and responds on `http://localhost/`

## 2. Dev Tooling

- [x] 2.1 Add `codeception/codeception` and scaffold `codeception.yml` with unit + functional suites
- [x] 2.2 Add `phpstan/phpstan` and create `phpstan.neon` configured at level 8
- [x] 2.3 Add `friendsofphp/php-cs-fixer` and create `.php-cs-fixer.php` with project rules
- [x] 2.4 Add Makefile targets: `test`, `analyse`, `cs-check`, `cs-fix`

## 3. Postgres Connection

- [x] 3.1 Add `doctrine/dbal` to `apps/core/composer.json`
- [x] 3.2 Configure `DATABASE_URL` in `apps/core/.env` pointing to local Postgres
- [x] 3.3 Add `.env.test` with test database URL
- [x] 3.4 Verify connection is established on app boot (no schema required)

## 4. LiteLLM Proxy

- [x] 4.1 Add `LiteLLM` service to `compose.yaml` with a local development config
- [x] 4.2 Expose `LiteLLM` on `http://localhost:4000/`
- [x] 4.3 Add `make logs-litellm` and document how to inspect LLM traffic through the proxy
- [x] 4.4 Verify the local `LiteLLM` endpoint responds after startup

## 5. Health Endpoint

- [x] 5.1 Create `HealthController` at `apps/core/src/Controller/HealthController.php`
- [x] 5.2 Map `GET /health` route returning JSON `{"status":"ok","service":"core-platform","version":"0.1.0"}`
- [x] 5.3 Write Codeception unit test for `HealthController`
- [x] 5.4 Write Codeception functional test: `GET /health` → `200 OK`, `application/json`

## 6. Playwright E2E

- [x] 6.1 Initialize Playwright in `tests/e2e/` (`npm init playwright@latest`)
- [x] 6.2 Write E2E test: `GET http://localhost/health` returns `200` with `status: ok`
- [x] 6.3 Enable Traefik REST API for local dev: add `insecure: true` under `api:` in `docker/traefik/traefik.yml`
- [x] 6.4 Expose Traefik API port in `compose.yaml`: add `"8080:8080"` to the `traefik` service ports
- [x] 6.5 Write E2E test: `GET http://localhost:8080/api/http/services` returns `200` (Traefik is up)
- [x] 6.6 Write E2E test: response from `/api/http/services` contains `core@docker` (core is registered)
- [x] 6.7 Write E2E test: `GET http://localhost:8080/api/http/routers` contains router `core` with `status: enabled`
- [x] 6.8 Add `make e2e` target
- [x] 6.9 Document Playwright setup and `make e2e` usage in `LOCAL_DEV.md`

## 7. Agent Contract And Docs

- [x] 7.1 Update architecture docs to describe `LiteLLM` as the local LLM gateway
- [x] 7.2 Update `docs/agent-prd-template.md` so LLM-capable agents declare proxy usage, model aliases, and safety limits
- [x] 7.3 Update `docs/prd/core-agent-openclaw.md` to route LLM calls through the platform-owned `LiteLLM` proxy in local dev

## 8. Validation

- [x] 8.1 `phpstan analyse` — zero errors at level 8
- [x] 8.2 `php-cs-fixer check` — no violations
- [x] 8.3 `codecept run` — all suites pass
- [x] 8.4 `make e2e` — Playwright E2E passes against running Docker stack
- [x] 8.5 Confirm `docker compose up` topology from `add-local-dev-compose-topology` is unaffected
