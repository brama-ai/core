## 1. PSR-4 File Renames
- [x] 1.1 Rename `AgentInvokeBridge.php` → `A2AClient.php`
- [x] 1.2 Rename `DiscoveryBuilder.php` → `SkillCatalogBuilder.php`
- [x] 1.3 Rename `AgentManifestFetcher.php` → `AgentCardFetcher.php`
- [x] 1.4 Rename `OpenClawSyncService.php` → `SkillCatalogSyncService.php`
- [x] 1.5 Rename `AgentInvokeBridgeTest.php` → `A2AClientTest.php`
- [x] 1.6 Rename `DiscoveryBuilderTest.php` → `SkillCatalogBuilderTest.php`
- [x] 1.7 Verify no remaining references to old file/class names in PHP code

## 2. PostgreSQL pgvector Support
- [x] 2.1 Change `compose.yaml` postgres image from `postgres:16-alpine` to `pgvector/pgvector:pg16`
- [x] 2.2 Add `docker/postgres/init/04_create_extensions.sql` to create `vector` extension in `news_maker_agent` and `news_maker_agent_test` databases

## 3. Multi-Tenant Agent Registration
- [x] 3.1 Update `AgentRegistrationController` to fall back to default tenant when TenantContext is not set
- [x] 3.2 Update Makefile `e2e-register-agents` SQL to include `tenant_id` in `agent_registry UPDATE` and `scheduled_jobs INSERT`
- [x] 3.3 Fix `ON CONFLICT` clause to use `(agent_name, job_name, tenant_id)` matching the unique constraint

## 4. E2E Test Fixes
- [x] 4.1 Fix `scheduler_test.js` — replace invalid `.skip()` with `xScenario()`
- [x] 4.2 Fix `deployment_config_test.js` — use `HELLO_URL`/`KNOWLEDGE_URL`/`NEWS_URL` env vars instead of Docker-internal hostnames
- [x] 4.3 Fix `traefik_test.js` — re-tag agent Traefik registration tests from `@smoke` to `@traefik`
- [x] 4.4 ~~Fix `slides_test.js`~~ — removed (slides service removed from project)

## 5. Verification
- [x] 5.1 `make verify-local-smoke` passes
- [x] 5.2 `make e2e-smoke` passes (18/18)
- [x] 5.3 `make e2e` — **182 passed**, 12 Traefik env-only failures (pre-existing), 1 skipped
- [x] 5.4 `phpstan analyse` passes (0 errors, level 8)
- [x] 5.5 `php-cs-fixer check` passes (0 violations)

## 6. Documentation
- [x] 6.1 Added `docs/guides/deployment/en/dockerfile-ownership.md` + `ua/` mirror; updated `docs/workspace-setup/{en,ua}/setup.md`
- [x] 6.2 Updated `templates/agent/Dockerfile` header with Dockerfile-ownership rule comment
