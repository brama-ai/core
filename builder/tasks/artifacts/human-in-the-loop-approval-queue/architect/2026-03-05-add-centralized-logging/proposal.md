# Change: Add Centralized Logging via OpenSearch

## Why
The platform has four applications (core, knowledge-agent, hello-agent, news-maker-agent) but no centralized logging. Logs are scattered across container stdout with no search, filtering, or trace correlation. Operators need a lightweight alternative to Kibana for viewing logs across all services with trace ID grouping. OpenSearch 2.11.1 is already running in the stack for knowledge-agent.

## What Changes
- Add Monolog OpenSearch handler to all three PHP apps (core, knowledge-agent, hello-agent) that buffers and sends logs via HTTP `_bulk` API
- Add Python logging handler for news-maker-agent with the same OpenSearch integration
- Generate and propagate trace IDs (`X-Trace-Id`) and request IDs (`X-Request-Id`) across all HTTP requests
- Add admin log viewer UI at `/admin/logs` with full-text search, level/app/date filters, and pagination
- Add trace detail page at `/admin/logs/trace/{traceId}` showing timeline view across services
- Add OpenSearch index template management (`logs:index:setup` command) with daily index rotation
- Add log cleanup command (`logs:cleanup`) supporting age-based (default 7 days) and size-based (default 2 GB) retention
- Add admin settings page for configuring log level and retention at runtime
- Add structured log statements to core controllers, services, and hello-agent
- Add logging to OpenClaw platform-tools plugin
- Add E2E tests for the log viewer

## Impact
- Affected code: All four apps (`apps/core/`, `apps/knowledge-agent/`, `apps/hello-agent/`, `apps/news-maker-agent/`), `docker/openclaw/plugins/platform-tools/index.js`, `compose.yaml`, `Makefile`
- New admin pages: `/admin/logs`, `/admin/logs/trace/{traceId}`, `/admin/settings` (replaces stub)
- New commands: `logs:index:setup`, `logs:cleanup`
- New Makefile targets: `logs-setup`, `logs-cleanup`
