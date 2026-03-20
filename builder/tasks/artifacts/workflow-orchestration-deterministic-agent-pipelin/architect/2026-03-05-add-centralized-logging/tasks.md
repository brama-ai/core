## 1. Logging Foundation (core)
- [x] 1.1 Create `TraceContext` value object for request-scoped trace/request IDs
- [x] 1.2 Create `TraceIdSubscriber` event subscriber (kernel.request/kernel.response) for ID generation and header propagation
- [x] 1.3 Create `OpenSearchProcessor` Monolog processor to enrich records with trace context
- [x] 1.4 Create `OpenSearchHandler` Monolog handler with buffered HTTP `_bulk` API writes
- [x] 1.5 Add `monolog.yaml` config with OpenSearch handler + stderr fallback + test null handler
- [x] 1.6 Install `symfony/monolog-bundle` and update `services.yaml`

## 2. Index Management + Cleanup (core)
- [x] 2.1 Create `LogIndexManager` for template management, daily indices, search queries
- [x] 2.2 Create `logs:index:setup` command
- [x] 2.3 Create `logs:cleanup` command with age-based and size-based retention + dry-run mode

## 3. Admin Log Viewer (core)
- [x] 3.1 Create `LogsController` for `/admin/logs` with search, filters, pagination
- [x] 3.2 Create `LogTraceController` for `/admin/logs/trace/{traceId}` trace timeline
- [x] 3.3 Create log viewer and trace templates
- [x] 3.4 Add "Логи" navigation to admin sidebar
- [x] 3.5 Add CSS styles for log viewer (badges, pagination, trace timeline)

## 4. Propagate to PHP Agents
- [x] 4.1 Copy Logging classes and monolog config to knowledge-agent
- [x] 4.2 Copy Logging classes and monolog config to hello-agent
- [x] 4.3 Install `symfony/monolog-bundle` in both agents

## 5. Python Agent Integration
- [x] 5.1 Create trace middleware for FastAPI (news-maker-agent)
- [x] 5.2 Create Python OpenSearch logging handler
- [x] 5.3 Update `main.py` and `config.py` with middleware and handler registration

## 6. Docker + Makefile
- [x] 6.1 Add `OPENSEARCH_URL` and `depends_on: opensearch` to hello-agent and news-maker-agent in compose.yaml
- [x] 6.2 Add `logs-setup` and `logs-cleanup` Makefile targets

## 7. Admin Log Level Configuration
- [x] 7.1 Create `LogSettingsProvider` for file-based settings (`var/log_settings.json`)
- [x] 7.2 Update `OpenSearchHandler` to use configurable log level
- [x] 7.3 Replace stub `SettingsController` with log settings form (level, retention, max size)
- [x] 7.4 Create settings template with form UI

## 8. Log Instrumentation (core)
- [x] 8.1 Add logger to `InvokeController` (incoming invoke, auth failures)
- [x] 8.2 Add logger to `AgentInvokeBridge` (A2A calls, agent disabled, HTTP failures)
- [x] 8.3 Add logger to `OpenClawSyncService` (push success/failure)
- [x] 8.4 Add logger to `DiscoveryController` (cache hits, requests)
- [x] 8.5 Add logger to `AgentHealthPollerCommand` (health results, threshold breaches)
- [x] 8.6 Add logger to `AgentRegistryRepository` (enable/disable/register)

## 9. Log Instrumentation (hello-agent)
- [x] 9.1 Add logger to `A2AController` (incoming requests, errors)
- [x] 9.2 Add logger to `HelloA2AHandler` (greeting processed)

## 10. OpenClaw Plugin Logging
- [x] 10.1 Add detailed `api.log()` calls to platform-tools plugin (discovery, invocations, errors)

## 11. E2E Tests
- [x] 11.1 Create `LogsPage` page object and `logs_test.js` E2E tests
- [x] 11.2 Register page object in codecept config

## 12. Documentation
- [x] 12.1 Create `docs/features/` directory with logging feature documentation
- [x] 12.2 Document PHP/Python/TS logging patterns and best practices

## 13. Quality Checks
- [x] 13.1 Run PHPStan, CS check, Codeception tests for core and hello-agent
- [x] 13.2 Run E2E tests
