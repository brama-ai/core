# Tasks: Fix E2E Agent Health Badge Not Showing After Discovery

## 1. Extract Health Check Into Reusable Service
- [x] 1.1 Create `src/AgentRegistry/AgentHealthChecker.php` — extract `checkHealth(string $url): bool` from `AgentHealthPollerCommand`
- [x] 1.2 Refactor `AgentHealthPollerCommand` to delegate to `AgentHealthChecker`
- [x] 1.3 Register `AgentHealthChecker` as a Symfony service (autowired)
- [x] 1.4 Verify: `phpstan analyse` passes, existing health poller unit/functional tests still green

## 2. Inline Health Probe on Registration
- [x] 2.1 Inject `AgentHealthChecker` and `AgentRegistryRepository` into `AgentRegistrationController`
- [x] 2.2 After `register()`, if manifest contains `health_url`, call `AgentHealthChecker::check()` and update `health_status` via `updateHealthStatus()`
- [x] 2.3 Include resolved `health_status` in registration JSON response
- [x] 2.4 Verify: `phpstan analyse` passes, `AgentRegistryApiCest` functional tests still green

## 3. Add `health_url` to E2E Registration Payloads
- [x] 3.1 Update Makefile `e2e-register-agents` — add `"health_url":"http://hello-agent-e2e/health"` to hello-agent payload
- [x] 3.2 Update Makefile `e2e-register-agents` — add `"health_url":"http://knowledge-agent-e2e/health"` to knowledge-agent payload
- [x] 3.3 Update Makefile `e2e-register-agents` — add `"health_url":"http://news-maker-agent-e2e:8000/health"` to news-maker-agent payload
- [x] 3.4 Update Makefile `e2e-register-agents` — add `"health_url":"http://dev-reporter-agent-e2e/health"` to dev-reporter-agent payload
- [x] 3.5 Update `news_maker_admin_test.js` Before hook — add `health_url` to registration payload
- [x] 3.6 Update `knowledge_admin_test.js` Before hook — add `health_url` to registration payload
- [x] 3.7 Verify: `make e2e-register-agents` succeeds with updated payloads

## 4. Add Health Poll to E2E Preparation
- [x] 4.1 Add `$(E2E_COMPOSE) exec -T core-e2e php bin/console app:agent-health-poll` to `e2e-prepare` target after `e2e-register-agents`
- [x] 4.2 Verify: after `make e2e-prepare`, all agents have `health_status != 'unknown'` in DB

## 5. Verify E2E Tests Pass
- [x] 5.1 Run `hello_agent_test.js` — verify `hello-agent is present and healthy after discovery` passes
- [x] 5.2 Run `news_maker_admin_test.js` — verify `news-maker-agent is present and healthy after discovery` passes
- [x] 5.3 Run full E2E suite — verify no regressions

## 6. Documentation
- [x] 6.1 Update `docs/agent-requirements/conventions.md` if it exists — document that `health_url` in manifest triggers immediate health probe on registration
- [x] 6.2 Add inline code comments explaining the health probe flow in `AgentRegistrationController`

## 7. Quality Checks
- [x] 7.1 `phpstan analyse` — zero errors at level 8
- [x] 7.2 `php-cs-fixer check` — no style violations
- [x] 7.3 `codecept run` — all unit + functional suites pass (2 pre-existing failures unrelated to this change)
- [x] 7.4 `make e2e` — E2E suite passes (specifically health badge tests)
