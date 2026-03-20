<!-- batch: 20260319_082138 | status: pass | duration: 377s | branch: pipeline/create-e2e-testing-architecture-mermaid-diagram -->
<!-- priority: 3 -->
# Create E2E testing architecture Mermaid diagram

Створити Mermaid діаграму архітектури E2E тестування платформи.

## Context

Платформа має повну E2E ізоляцію ресурсів:
- **PostgreSQL**: `_test` suffix databases (ai_community_platform_test, knowledge_agent_test, etc.)
- **Redis**: Odd DB numbers for E2E (prod DB 0 → test DB 1, prod DB 2 → test DB 3)
- **OpenSearch**: `_test` index suffix
- **RabbitMQ**: Separate `/test` vhost
- **Services**: Dedicated E2E containers on separate ports (core-e2e:18080, agents:18083-18087, openclaw:28789)

## Task

Create a comprehensive Mermaid diagram in `docs/architecture/ua/e2e-testing.md` and `docs/architecture/en/e2e-testing.md` (bilingual) showing:

1. **Production stack** vs **E2E stack** side by side
2. How each service (Core, Knowledge, News-Maker, Hello, Dev-Reporter) connects to its test DB
3. Resource isolation: PostgreSQL (_test DBs), Redis (odd DBs), OpenSearch (_test indices), RabbitMQ (/test vhost)
4. Test runner flow: `make e2e` → e2e-db-init → e2e-rabbitmq-init → e2e-prepare → codeceptjs+playwright
5. Port mappings (prod vs E2E)

Use Mermaid `graph TD` or `flowchart` syntax. Make it clear and readable.

## Key files to read

- docker/postgres/init/03_create_test_databases.sql
- compose.core.yaml (core-e2e service)
- compose.agent-knowledge.yaml (knowledge-agent-e2e)
- Makefile (e2e targets)
- openspec/specs/e2e-testing/spec.md

## Key files to create

- docs/architecture/ua/e2e-testing.md
- docs/architecture/en/e2e-testing.md

## Validation

- Mermaid diagram renders correctly (valid syntax)
- Both UA and EN versions exist
- Diagram covers all 5 resource types and their isolation

---

## Pipeline Run

- **Batch:** 20260319_082138
- **Status:** PASS
- **Duration:** 377s (6m 17s)
- **Branch:** `pipeline/create-e2e-testing-architecture-mermaid-diagram`
- **Date:** 2026-03-19 08:27:59

### Plan

- **Profile:** docs-only
- **Agents:** documenter → summarizer
- **Reasoning:** Task is to create Mermaid diagrams in documentation files. No code changes, no API changes, no migrations. Just creating 2 markdown files with diagrams.

### Agent Steps

| Agent | Model | Duration | Tokens (in/out) | Cache | Cost | Status |
|-------|-------|----------|-----------------|-------|------|--------|
M	.claude/commands/openspec/apply.md
M	.claude/commands/openspec/proposal.md
M	.devcontainer/Dockerfile
M	.devcontainer/post-create.sh
M	.opencode/pipeline/.batch.lock
D	.opencode/pipeline/logs/20260311_004057_planner.meta.json
D	.opencode/pipeline/logs/20260311_004355_auditor.meta.json
D	.opencode/pipeline/logs/20260311_004355_coder.meta.json
D	.opencode/pipeline/logs/20260311_004355_planner.meta.json
D	.opencode/pipeline/logs/20260311_004355_tester.meta.json
D	.opencode/pipeline/logs/20260311_004355_validator.meta.json
M	apps/core/config/packages/security.yaml
M	apps/core/config/reference.php
M	apps/core/config/services.yaml
M	apps/core/src/A2AGateway/A2AClient.php
M	apps/core/src/AgentRegistry/AgentRegistryAuditLogger.php
M	apps/core/src/AgentRegistry/AgentRegistryRepository.php
M	apps/core/src/Command/SchedulerRunCommand.php
M	apps/core/src/Controller/Admin/AgentSettingsController.php
M	apps/core/src/Controller/Admin/AgentsController.php
M	apps/core/src/Controller/Admin/ChatDetailController.php
M	apps/core/src/Controller/Admin/ChatsController.php
M	apps/core/src/Controller/Admin/DashboardController.php
M	apps/core/src/Controller/Admin/LogTraceController.php
M	apps/core/src/Controller/Admin/LogsController.php
M	apps/core/src/Controller/Admin/SchedulerController.php
M	apps/core/src/Controller/Admin/SchedulerJobLogsController.php
M	apps/core/src/Controller/Admin/SettingsController.php
M	apps/core/src/Controller/Api/Internal/AgentInstallController.php
M	apps/core/src/Controller/EdgeAuth/LoginController.php
M	apps/core/src/Scheduler/ScheduledJobRepository.php
M	apps/core/templates/admin/layout.html.twig
M	apps/core/tests/Unit/A2AGateway/A2AClientTest.php
M	apps/core/tests/Unit/AgentRegistry/AgentRegistryRepositoryTest.php
M	apps/core/tests/_support/Helper/Functional.php
M	apps/core/tests/_support/_generated/FunctionalTesterActions.php
M	builder/README.md
M	docs/guides/external-agents/en/onboarding.md
M	docs/guides/external-agents/en/repository-structure.md
M	docs/guides/external-agents/ua/onboarding.md
M	openspec/AGENTS.md
M	openspec/changes/add-agent-repo-documentation/tasks.md
D	openspec/changes/add-core-e2e-test-database-isolation/design.md
D	openspec/changes/add-core-e2e-test-database-isolation/proposal.md
D	openspec/changes/add-core-e2e-test-database-isolation/specs/e2e-testing/spec.md
D	openspec/changes/add-core-e2e-test-database-isolation/specs/local-dev-runtime/spec.md
D	openspec/changes/add-core-e2e-test-database-isolation/tasks.md
M	openspec/changes/add-platform-coder-agent/design.md
M	openspec/changes/add-platform-coder-agent/proposal.md
M	openspec/changes/add-platform-coder-agent/tasks.md
M	openspec/changes/add-telegram-bot-integration/specs/telegram-channel-posting/spec.md
M	openspec/changes/add-telegram-bot-integration/specs/telegram-event-bus/spec.md
M	openspec/changes/add-telegram-bot-integration/specs/telegram-interactive-flows/spec.md
M	openspec/changes/add-tenant-management/design.md
M	openspec/changes/add-tenant-management/proposal.md
M	openspec/changes/add-tenant-management/specs/tenant-management/spec.md
M	openspec/changes/add-tenant-management/tasks.md
M	scripts/sync-skills.sh
M	tests/e2e/codecept.conf.js
M	tests/e2e/package-lock.json
Your branch is ahead of 'origin/main' by 23 commits.
  (use "git push" to publish your local commits)
