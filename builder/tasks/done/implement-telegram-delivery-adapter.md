<!-- batch: 20260320_020826 | status: pass | duration: 15s | branch: pipeline/implement-telegram-delivery-channel-adapter-sectio -->
<!-- priority: 1 -->
# Implement Telegram Delivery Channel Adapter (Section 7)

Реалізувати Delivery Channel Adapter для Telegram — адаптер, який маппить абстрактний DeliveryPayload на виклики TelegramSender::send(). Це секція 7 з tasks.md інтеграції Telegram.

## OpenSpec

- Proposal: openspec/changes/add-telegram-bot-integration/proposal.md
- Tasks: openspec/changes/add-telegram-bot-integration/tasks.md (Section 7)
- Spec: openspec/changes/add-telegram-bot-integration/specs/telegram-channel-posting/spec.md

## Context

Existing code to build upon:
- `apps/core/src/Telegram/Service/TelegramSender.php` — high-level send interface with `send(botId, chatId, text, options)` where options include `thread_id`, `reply_to_message_id`, `parse_mode`, `reply_markup`
- `apps/core/src/Telegram/Api/TelegramApiClient.php` — low-level Telegram Bot API HTTP client
- `apps/core/src/Telegram/Service/TelegramBotRegistry.php` — loads bot configs from DB
- DTO patterns in `apps/core/src/Telegram/DTO/` (NormalizedEvent, NormalizedChat, etc.)

There are NO existing `ChannelAdapterInterface`, `DeliveryPayload`, `DeliveryTarget`, or `DeliveryResult` types — these need to be created as part of this task.

Follow existing naming/structure patterns:
- DTOs go in `apps/core/src/Telegram/DTO/` or a new `apps/core/src/Delivery/` namespace
- Services follow readonly constructor injection pattern
- All classes use `declare(strict_types=1)` and `final` where appropriate

## Tasks from tasks.md

- [ ] 7.1 Create `TelegramDeliveryAdapter` implementing `ChannelAdapterInterface` — maps `DeliveryPayload` to `TelegramSender::send()` call
- [ ] 7.2 Map `DeliveryTarget.address` to Telegram `chat_id` + optional `thread_id` (format: `chat_id` or `chat_id:thread_id`)
- [ ] 7.3 Map `DeliveryPayload.content_type` to Telegram parse mode (`markdown` → MarkdownV2, `text` → plain, `card` → HTML with formatting)
- [ ] 7.4 Return `DeliveryResult` with Telegram's `message_id` as `external_message_id`
- [ ] 7.5 Register adapter in `services.yaml` with tag `delivery.adapter` and type `telegram`
- [ ] 7.6 Write unit tests for TelegramDeliveryAdapter

## Key files to create/update

### New files:
- `apps/core/src/Delivery/ChannelAdapterInterface.php` — interface with `deliver(DeliveryPayload): DeliveryResult`
- `apps/core/src/Delivery/DeliveryPayload.php` — DTO: content_text, content_type (markdown|text|card), target (DeliveryTarget), options
- `apps/core/src/Delivery/DeliveryTarget.php` — DTO: channel_type (telegram), address (chat_id or chat_id:thread_id), bot_id
- `apps/core/src/Delivery/DeliveryResult.php` — DTO: success, external_message_id, error
- `apps/core/src/Telegram/Delivery/TelegramDeliveryAdapter.php` — implements ChannelAdapterInterface
- `apps/core/tests/Unit/Telegram/Delivery/TelegramDeliveryAdapterTest.php` — unit tests

### Files to update:
- `apps/core/config/services.yaml` — register TelegramDeliveryAdapter with `delivery.adapter` tag
- `openspec/changes/add-telegram-bot-integration/tasks.md` — mark section 7 items as [x]

## Implementation Details

### Address parsing (7.2)
`DeliveryTarget.address` format:
- `"123456789"` → chat_id=123456789, no thread
- `"123456789:42"` → chat_id=123456789, thread_id=42

### Content type mapping (7.3)
- `markdown` → parse_mode=MarkdownV2
- `text` → no parse_mode (plain text)
- `card` → parse_mode=HTML (formatted card)

### DeliveryResult (7.4)
- On success: `success=true`, `external_message_id` = Telegram's `message_id` from API response
- On failure: `success=false`, `error` = error description

## Validation

- PHPStan level 8 passes
- CS-Fixer passes
- Unit tests for TelegramDeliveryAdapter pass (test all content types, address formats, error handling)
- `php bin/codecept run Unit Telegram/Delivery` passes

---

## Pipeline Run

- **Batch:** 20260320_020826
- **Status:** PASS
- **Duration:** 15s (0m 15s)
- **Branch:** `pipeline/implement-telegram-delivery-channel-adapter-sectio`
- **Date:** 2026-03-20 02:08:43

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
