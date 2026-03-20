<!-- batch: 20260320_020845 | status: pass | duration: 17s | branch: pipeline/docs-leaf-directory-rule -->
<!-- priority: 1 -->
# Нормалізувати структуру каталогу docs/ під leaf-directory rule

Перемістити всі .md файли, що порушують leaf-directory rule, у leaf-директорії.
Leaf-directory rule: якщо директорія має піддиректорії, вона НЕ МОЖЕ містити .md файли напряму. Єдиний виняток — `docs/index.md`.

## Context

Конвенція визначена в `skills/documentation/SKILL.md`. Знайдено 8 директорій з порушеннями (~60+ файлів).

## Порушення та план виправлення

### 1. `docs/` (root) — 5 файлів
- `ROADMAP_GUIDELINES.md` → `docs/plans/roadmap-guidelines.md` (english-only leaf)
- `WORKFLOW_GUIDELINES.md` → `docs/plans/workflow-guidelines.md` (english-only leaf)
- `scheduler.md` → `docs/features/scheduler/en/scheduler.md` + створити `ua/` mirror
- `local-dev.md` → `docs/setup/local-dev/en/local-dev.md` + створити `ua/` mirror
- `CLAUDE_VSCODE_SETUP.md` → `docs/setup/claude-vscode/en/claude-vscode-setup.md` + створити `ua/` mirror
- **НЕ чіпати** `docs/index.md` (дозволений виняток)

### 2. `docs/features/` — 6 файлів
- `pipeline-batch.md` + `pipeline-batch.en.md` → `docs/features/pipeline-batch/ua/pipeline-batch.md` + `docs/features/pipeline-batch/en/pipeline-batch.md`
- `litellm.md` + `litellm.en.md` → `docs/features/litellm/ua/litellm.md` + `docs/features/litellm/en/litellm.md`
- `logging.md` → `docs/features/logging/en/logging.md` + створити `ua/` mirror
- `README.md` → видалити або перемістити зміст в `docs/index.md` якщо унікальний

### 3. `docs/guides/` — 1 файл
- `tenant-context.md` → `docs/guides/tenant-context/en/tenant-context.md` + створити `ua/` mirror

### 4. `docs/guides/external-agents/en/` — має піддиректорію `template/`
- Перемістити `template/` → `docs/templates/external-agents/` щоб `en/` залишився leaf

### 5. `docs/templates/` — 2 файли
- `development-plan-template.md`, `agent-prd-template.md` → `docs/templates/general/development-plan-template.md`, `docs/templates/general/agent-prd-template.md`

### 6. `docs/neuron-ai/` — 36+ файлів (найбільше порушення)
- Перемістити всі .md файли → `docs/neuron-ai/reference/` (leaf, english-only)

### 7. `docs/neuron-ai/examples/` — 1 файл
- `index.md` → `docs/neuron-ai/examples/overview/index.md` (або перейменувати в leaf)
- Альтернатива: об'єднати `guide/` та `vendor/` в flat структуру і залишити `index.md`

### 8. `docs/fetched/a2a-protocol-org/` — 1 файл
- `README.md` → `docs/fetched/a2a-protocol-org/en/README.md`

## Key files to create/update

- Всі переміщені файли (див. план вище)
- `docs/index.md` — оновити всі посилання на нові шляхи
- Будь-які .md файли в репозиторії, що мають перехресні посилання на переміщені файли (grep для старих шляхів)

## Validation

- Жодна директорія з піддиректоріями не має .md файлів (крім `docs/index.md`)
- Всі посилання в `docs/index.md` вказують на існуючі файли
- Grep по репозиторію не знаходить битих посилань на старі шляхи
- Зміст документів НЕ змінено — тільки переміщення
- Білінгвальна ua/en конвенція збережена для bilingual domains
- Перевірка: `find docs/ -name "*.md" | while read f; do d=$(dirname "$f"); if [ -d "$d" ] && [ "$(find "$d" -mindepth 1 -maxdepth 1 -type d | head -1)" ] && [ "$f" != "docs/index.md" ]; then echo "VIOLATION: $f"; fi; done` — повинно бути порожнім

---

## Pipeline Run

- **Batch:** 20260320_020845
- **Status:** PASS
- **Duration:** 17s (0m 17s)
- **Branch:** `pipeline/docs-leaf-directory-rule`
- **Date:** 2026-03-20 02:09:03

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
