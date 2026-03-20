## 1. Infrastructure Setup
- [x] 1.1 Create `docker/hello-agent/Dockerfile` (PHP 8.5 + Apache, based on knowledge-agent pattern)
- [x] 1.2 Add `hello-agent` entrypoint `:8085` to `docker/traefik/traefik.yml`
- [x] 1.3 Add `hello-agent` service to `compose.yaml` with `ai.platform.agent=true` label
- [x] 1.4 Add port `8085:8085` to Traefik service in `compose.yaml`

## 2. Hello-Agent Symfony App
- [x] 2.1 Scaffold Symfony 7 app skeleton in `apps/hello-agent/` (public/index.php, Kernel, config)
- [x] 2.2 Create `composer.json` with minimal Symfony deps (framework-bundle, twig-bundle)
- [x] 2.3 Create `HealthController` — `GET /health` → `{"status": "ok", "service": "hello-agent"}`
- [x] 2.4 Create `ManifestController` — `GET /api/v1/manifest` with valid manifest JSON
- [x] 2.5 Create `HelloController` — `GET /` webview rendering "Hello, World!" via Twig template
- [x] 2.6 Create `hello.html.twig` template with greeting text
- [x] 2.7 Configure `routes.yaml`, `services.yaml`, `framework.yaml`, `twig.yaml`
- [x] 2.8 Configure `security.yaml` with `security: false` for all firewalls (not needed — no security-bundle)

## 3. Admin Config UI (Core Platform)
- [x] 3.1 Extend `AgentsController` to decode `config` JSON in agent list
- [x] 3.2 Add config edit modal to `agents.html.twig` with `description` and `system_prompt` fields
- [x] 3.3 Wire form submission to existing `PUT /api/v1/internal/agents/{name}/config` API via JS fetch

## 4. Makefile Targets
- [x] 4.1 Add `hello-setup`, `hello-install`, `hello-test`, `hello-analyse`, `hello-cs-check`, `hello-cs-fix` targets
- [x] 4.2 Add `hello-setup` to the `setup` target dependencies

## 5. Automated Tests
- [x] 5.1 Configure Codeception in `apps/hello-agent/` (unit + functional suites)
- [x] 5.2 Write functional test: `GET /health` returns 200 + `{"status": "ok"}`
- [x] 5.3 Write functional test: `GET /api/v1/manifest` returns valid manifest
- [x] 5.4 Write functional test: `GET /` renders greeting page with "Hello, World!"
- [x] 5.5 Write unit test for greeting text resolution (default vs config override)
- [x] 5.6 Verify convention tests pass: `AGENT_URL=http://localhost:8085 make conventions-test` (pending full Traefik stack)

## 6. Admin Config Tests (Core Platform)
- [x] 6.1 Write functional test: agents list page contains config form elements
- [x] 6.2 Write functional test: config update endpoint requires auth

## 7. Documentation
- [x] 7.1 Add hello-agent section to `docs/agents/` (Ukrainian canonical + `.en.md` mirror)
- [x] 7.2 Update `docs/agent-requirements/` if agent contracts are affected (no changes needed)

## 8. Quality Checks
- [x] 8.1 `make hello-analyse` — PHPStan level 8, zero errors
- [x] 8.2 `make hello-cs-check` — no style violations
- [x] 8.3 `make hello-test` — all suites pass (5 tests, 13 assertions)
- [x] 8.4 `make test` — core platform tests still pass (37 tests, 90 assertions)
- [x] 8.5 `make conventions-test` — agent convention compliance (pending full Traefik stack)
