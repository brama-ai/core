<!-- batch: 20260320_020749 | status: pass | duration: 16s | branch: pipeline/implement-change-add-pipeline-cost-tracker -->
<!-- priority: 3 -->
# Implement change: add-pipeline-cost-tracker

Реалізувати модуль підрахунку вартості AI провайдерів в builder pipeline.

## OpenSpec

- Proposal: openspec/changes/add-pipeline-cost-tracker/proposal.md
- Design: openspec/changes/add-pipeline-cost-tracker/design.md
- Tasks: openspec/changes/add-pipeline-cost-tracker/tasks.md
- Spec: openspec/changes/add-pipeline-cost-tracker/specs/cost-tracking/spec.md

## Context

Модуль `builder/cost-tracker.sh` — standalone bash script який:
- Має pricing data для всіх моделей (Anthropic, OpenAI, Google, OpenRouter)
- Читає `.meta.json` файли для отримання token counts
- Рахує cost per step і daily aggregation
- Інтегрується з pipeline.sh (emit COST events) та monitor (display)

ENV конфігурація тарифних планів з коментарями в `.env.local.example`.

Read design.md for full architecture and data model.

## Key files to create

- `builder/cost-tracker.sh` — pricing module
- Update `.env.local.example` — add plan variables with comments

## Key files to modify

- `builder/pipeline.sh` — source cost-tracker, emit COST events after agents
- `builder/pipeline-batch.sh` — include cost in task metadata
- `builder/monitor/pipeline-monitor.sh` — render cost footer in Activity tab
- `builder/AGENTS.md` — add cost tracking documentation

## Validation

- `bash -n builder/cost-tracker.sh` passes
- `bash -n builder/pipeline.sh` passes
- `bash -n builder/monitor/pipeline-monitor.sh` passes
- tasks.md items marked [x]

---

## Pipeline Run

- **Batch:** 20260320_020749
- **Status:** PASS
- **Duration:** 16s (0m 16s)
- **Branch:** `pipeline/implement-change-add-pipeline-cost-tracker`
- **Date:** 2026-03-20 02:08:06

### Agent Steps

| Agent | Model | Duration | Tokens (in/out) | Cache | Cost | Status |
|-------|-------|----------|-----------------|-------|------|--------|
M	.claude/commands/openspec/apply.md
M	.claude/commands/openspec/proposal.md
M	.devcontainer/Dockerfile
M	.devcontainer/devcontainer.json
M	.devcontainer/docker-compose.yml
M	.devcontainer/post-create.sh
M	.gitignore
M	.opencode/agents/architect.md
M	.opencode/agents/auditor.md
M	.opencode/agents/coder.md
M	.opencode/agents/documenter.md
M	.opencode/agents/planner.md
M	.opencode/agents/summarizer.md
M	.opencode/agents/tester.md
M	.opencode/agents/validator.md
M	.opencode/pipeline/.batch.lock
M	.opencode/pipeline/handoff.md
D	.opencode/pipeline/logs/20260311_004057_planner.meta.json
D	.opencode/pipeline/logs/20260311_004355_auditor.meta.json
D	.opencode/pipeline/logs/20260311_004355_coder.meta.json
D	.opencode/pipeline/logs/20260311_004355_planner.meta.json
D	.opencode/pipeline/logs/20260311_004355_tester.meta.json
D	.opencode/pipeline/logs/20260311_004355_validator.meta.json
D	.opencode/pipeline/logs/20260311_081120_coder.meta.json
D	.opencode/pipeline/logs/20260311_104327_coder.meta.json
D	.opencode/pipeline/logs/20260311_104327_tester.meta.json
D	.opencode/pipeline/logs/20260311_104327_validator.meta.json
M	Makefile
M	apps/core/.env
M	apps/core/config/packages/security.yaml
M	apps/core/config/services.yaml
M	apps/core/src/A2AGateway/A2AClient.php
M	apps/core/src/A2AGateway/SkillCatalogBuilder.php
M	apps/core/src/A2AGateway/SkillCatalogSyncService.php
M	apps/core/src/AgentRegistry/AgentRegistryAuditLogger.php
M	apps/core/src/AgentRegistry/AgentRegistryRepository.php
M	apps/core/src/Command/OpenspecProposeCommand.php
M	apps/core/src/Command/SchedulerRunCommand.php
M	apps/core/src/Command/TelegramPollCommand.php
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
M	apps/core/src/Controller/Api/A2AGateway/DiscoveryController.php
M	apps/core/src/Controller/Api/Internal/AgentInstallController.php
M	apps/core/src/Controller/EdgeAuth/LoginController.php
M	apps/core/src/Controller/HealthController.php
M	apps/core/src/Scheduler/ScheduledJobRepository.php
M	apps/core/templates/admin/layout.html.twig
M	apps/core/tests/Functional/Api/A2AGateway/DiscoveryControllerCest.php
M	apps/core/tests/Functional/Api/A2AGateway/SendMessageControllerCest.php
M	apps/core/tests/Unit/A2AGateway/A2AClientTest.php
M	apps/core/tests/Unit/A2AGateway/SkillCatalogBuilderTest.php
M	apps/core/tests/Unit/AgentRegistry/AgentRegistryRepositoryTest.php
M	apps/core/tests/Unit/Controller/HealthControllerTest.php
M	apps/core/tests/_support/Helper/Functional.php
M	apps/core/tests/_support/_generated/FunctionalTesterActions.php
M	apps/hello-agent/src/Controller/HealthController.php
M	apps/knowledge-agent/.env
M	apps/knowledge-agent/composer.json
M	apps/knowledge-agent/config/services.yaml
M	apps/knowledge-agent/src/Command/KnowledgeWorkerCommand.php
M	apps/knowledge-agent/src/Controller/Admin/KnowledgeAdminController.php
M	apps/knowledge-agent/src/Controller/HealthController.php
M	apps/knowledge-agent/src/Workflow/KnowledgeExtractionAgent.php
M	apps/knowledge-agent/src/Workflow/KnowledgeExtractionWorkflow.php
M	apps/knowledge-agent/tests/Functional/Api/KnowledgeApiCest.php
M	apps/knowledge-agent/tests/Functional/Api/SearchApiCest.php
M	apps/knowledge-agent/tests/_output/App.Tests.Functional.Admin.SettingsApiCest.settingsApiRejectsEmptyInstructions.fail.json
M	apps/knowledge-agent/tests/_output/App.Tests.Functional.Admin.SettingsApiCest.settingsApiReturnsDefaults.fail.html
M	apps/news-maker-agent/app/routers/health.py
M	builder/README.md
M	builder/monitor/pipeline-monitor.sh
M	builder/pipeline-batch.sh
M	builder/pipeline.sh
M	builder/tests/test-pipeline-lifecycle.sh
M	compose.agent-hello.yaml
M	compose.agent-knowledge.yaml
M	compose.agent-news-maker.yaml
M	compose.core.yaml
M	compose.yaml
M	docker/openclaw/README.md
M	docs/guides/external-agents/en/onboarding.md
M	docs/guides/external-agents/en/repository-structure.md
M	docs/guides/external-agents/ua/onboarding.md
M	docs/index.md
M	index.md
M	openspec/AGENTS.md
M	openspec/changes/add-agent-repo-documentation/tasks.md
D	openspec/changes/add-core-e2e-test-database-isolation/design.md
D	openspec/changes/add-core-e2e-test-database-isolation/proposal.md
D	openspec/changes/add-core-e2e-test-database-isolation/specs/e2e-testing/spec.md
D	openspec/changes/add-core-e2e-test-database-isolation/specs/local-dev-runtime/spec.md
D	openspec/changes/add-core-e2e-test-database-isolation/tasks.md
M	openspec/changes/add-deep-crawling/tasks.md
M	openspec/changes/add-dual-docker-kubernetes-deployment/tasks.md
M	openspec/changes/add-knowledge-base-agent/tasks.md
M	openspec/changes/add-openclaw-agent-discovery/tasks.md
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
M	tests/e2e/support/pages/AgentsPage.js
M	tests/e2e/tests/admin/agents_test.js
M	tests/e2e/tests/smoke/health_test.js
Your branch is ahead of 'origin/main' by 25 commits.
  (use "git push" to publish your local commits)
