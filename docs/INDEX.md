# Documentation Index

Agent-facing index for `brama-core/docs/`.

This index points to the main project documentation roots and the most important leaf documents.
For bilingual sections, links point to the English version by default.

## Agent Requirements

- `agent-requirements/agent-state-model.md` — agent lifecycle and state transition model.
- `agent-requirements/conventions.md` — shared agent conventions and platform expectations.
- `agent-requirements/e2e-cuj-matrix.md` — end-to-end critical user journey coverage matrix.
- `agent-requirements/e2e-testing.md` — E2E contract and topology expectations.
- `agent-requirements/observability-requirements.md` — tracing, logging, and observability requirements for agents.
- `agent-requirements/storage-provisioning.md` — storage provisioning contract for agents.
- `agent-requirements/test-cases.md` — baseline agent test case catalog.

## Agents

- `agents/en/hello-agent.md` — Hello agent behavior, contracts, and operations.
- `agents/en/knowledge-base-agent.md` — knowledge base agent overview and runtime contract.
- `agents/en/news-maker-agent.md` — news-maker agent admin and runtime behavior.
- `agents/en/dev-reporter-agent.md` — dev-reporter agent overview and pipeline reporting behavior.
- `agents/en/dev-agent.md` — developer-facing agent overview.
- `agents/en/wiki-agent.md` — wiki agent product/runtime documentation.
- `agents/en/news-digest-prd.md` — news digest product requirements.
- `agents/en/knowledge-extractor-prd.md` — knowledge extractor product requirements.
- `agents/en/locations-catalog-prd.md` — locations catalog product requirements.
- `agents/en/anti-fraud-signals-prd.md` — anti-fraud signals product requirements.

## Decisions

- `decisions/adr_0002_openclaw_role.md` — architectural decision for OpenClaw role in the platform.

## Runtime / Configuration

- `guides/deployment/en/deployment-configuration.md` — environment and deployment configuration reference across deployment modes.

## Features

- `features/litellm/en/litellm.md` — LiteLLM gateway access, credentials, and troubleshooting.
- `features/litellm-requests/overview.md` — request metadata flow from agents to LiteLLM and Langfuse.
- `features/litellm-requests/langfuse-integration.md` — Langfuse integration details and debugging.
- `features/litellm-requests/tracing-contract.md` — tracing metadata contract for LiteLLM requests.
- `features/litellm-requests/examples.md` — concrete request metadata examples.
- `features/litellm-requests/migration-notes.md` — breaking changes and migration notes for LiteLLM request metadata.
- `features/dashboard-metrics/en/dashboard-metrics.md` — admin dashboard metrics and KPIs.
- `features/i18n-locale/en/i18n-locale.md` — locale switcher and i18n behavior.
- `features/logging/en/logging.md` — structured logging model and operations.
- `features/pipeline/en/pipeline.md` — pipeline runtime and operator behavior.
- `features/pipeline/en/root-cause-analysis.md` — practical Foundry failure diagnosis and root cause workflow.
- `features/pipeline-batch/en/pipeline-batch.md` — batch pipeline execution model.
- `features/scheduler/en/scheduler.md` — scheduler internals, cron model, and admin behavior.
- `features/tenant-management/en/tenant-management.md` — tenant management feature behavior.
- `features/overview/ua/README.md` — Ukrainian overview landing page for feature docs.

## Fetched

- `fetched/a2a-protocol-org/en/README.md` — upstream A2A reference collection root.
- `fetched/a2a-protocol-org/en/what-is-a2a.md` — A2A overview.
- `fetched/a2a-protocol-org/en/agent-discovery.md` — A2A discovery reference.
- `fetched/a2a-protocol-org/en/key-concepts.md` — A2A concepts reference.
- `fetched/a2a-protocol-org/en/extensions.md` — A2A extensions reference.
- `fetched/a2a-protocol-org/en/streaming-and-async.md` — A2A async/streaming reference.
- `fetched/a2a-protocol-org/en/life-of-a-task.md` — A2A task lifecycle reference.
- `fetched/a2a-protocol-org/en/a2a-and-mcp.md` — A2A and MCP comparison/reference.
- `fetched/a2a-protocol-org/en/enterprise-ready.md` — enterprise readiness reference.

## Guides

- `guides/deployment/en/deployment-overview.md` — official deployment modes overview.
- `guides/deployment/en/deployment-topology.md` — supported deployment topologies and trade-offs.
- `guides/deployment/en/deployment.md` — Docker deployment guide.
- `guides/deployment/en/docker-install.md` — Docker install guide.
- `guides/deployment/en/docker-upgrade.md` — Docker upgrade guide.
- `guides/deployment/en/docker-troubleshooting.md` — Docker troubleshooting guide.
- `guides/deployment/en/docker-deployment-contract.md` — Docker deployment contract.
- `guides/deployment/en/docker-backup-restore.md` — Docker backup and restore guide.
- `guides/deployment/en/kubernetes-install.md` — Kubernetes installation guide.
- `guides/deployment/en/kubernetes-upgrade.md` — Kubernetes upgrade guide.
- `guides/deployment/en/k3s-storage-architecture.md` — k3s storage durability tiers, PVC strategy, and service matrix.
- `guides/deployment/en/k3s-storage-backup.md` — PostgreSQL backup/restore runbook, OpenSearch rebuild, Redis/RabbitMQ loss, and rollback strategy.
- `guides/deployment/en/k3s-storage-verification.md` — PVC bound verification, pod-restart survival tests, and storage gate checklist.
- `guides/external-agents/en/onboarding.md` — external agent onboarding guide.
- `guides/external-agents/en/operator-onboarding.md` — operator onboarding for external agents.
- `guides/external-agents/en/external-agent-workspace.md` — external agent workspace layout and contract.
- `guides/external-agents/en/repository-structure.md` — repository structure for external agents.
- `guides/external-agents/en/migration-playbook.md` — migration playbook for externalized agents.
- `guides/external-agents/en/pilot-agent-selection.md` — pilot agent selection rationale.
- `guides/devcontainer/en/architecture.md` — devcontainer architecture and purpose.
- `guides/env-checker/en/env-checker.md` — environment checker guide.
- `guides/coder-ui/en/core-admin.md` — coder UI and core admin workflow guide.
- `guides/oh-my-opencode/en/oh-my-opencode.md` — OpenCode workflow guide.
- `guides/oh-my-opencode/en/quickstart.md` — OpenCode quickstart.
- `../../docs/foundry-models/en/foundry-models.md` — Foundry model routing policy (moved to workspace root).
- `guides/tenant-context/en/tenant-context.md` — tenant isolation, RBAC, and repository scoping guide.

## Neuron AI

- `neuron-ai/reference/index.md` — Neuron AI reference index.
- `neuron-ai/reference/introduction.md` — Neuron AI introduction.
- `neuron-ai/reference/installation.md` — installation reference.
- `neuron-ai/reference/agent.md` — agent API reference.
- `neuron-ai/reference/workflow.md` — workflow reference.
- `neuron-ai/reference/tools.md` — tools integration reference.
- `neuron-ai/reference/mcp.md` — MCP integration reference.
- `neuron-ai/reference/testing.md` — testing reference.
- `neuron-ai/reference/monitoring-and-debugging.md` — monitoring/debugging reference.
- `neuron-ai/examples/overview/index.md` — Neuron AI examples overview.
- `neuron-ai/examples/guide/index.md` — Neuron AI examples guide.
- `neuron-ai/examples/vendor/` — mirrored upstream/vendor documentation corpus kept under examples; treat as imported reference material rather than curated platform docs.

## Plans

- `plans/platform-mvp-development-plan.md` — main MVP development plan.
- `plans/news-digest-development-plan.md` — news digest implementation plan.
- `plans/knowledge-base-agent-development-plan.md` — knowledge base agent plan.
- `plans/knowledge-extractor-development-plan.md` — knowledge extractor plan.
- `plans/locations-catalog-development-plan.md` — locations catalog plan.
- `plans/anti-fraud-signals-development-plan.md` — anti-fraud signals plan.
- `plans/openclaw-frontdesk-testing-plan.md` — OpenClaw frontdesk testing plan.
- `plans/openclaw-gateway-router-blueprint.md` — OpenClaw gateway router blueprint.
- `plans/openclaw-observability-rollout-plan.md` — OpenClaw observability rollout plan.
- `plans/telegram-openclaw-integration-plan.md` — Telegram/OpenClaw integration plan.
- `plans/openspec-audit-2026-03-05.md` — OpenSpec audit notes.
- `plans/roadmap-guidelines.md` — roadmap writing guidelines.
- `plans/workflow-guidelines.md` — workflow and pipeline guidelines.

## Product

- `product/en/platform-mvp-prd.md` — platform MVP product requirements.
- `product/en/architecture-overview.md` — platform architecture overview.
- `product/en/core-agent-openclaw.md` — core/OpenClaw relationship and flow.
- `product/en/brainstorm.md` — translated brainstorming and product exploration notes.

## Setup

- `setup/local-dev/en/local-dev.md` — local development guide, runtime URLs, credentials, and test commands.
- `setup/claude-vscode/en/claude-vscode-setup.md` — Claude Code VS Code setup guide.
- `setup/gemini-strategy/en/gemini-strategy.md` — Gemini strategy/setup guide.

## Specs

- `specs/en/README.md` — English specs overview.
- `specs/en/a2a-protocol.md` — A2A protocol spec.
- `specs/en/a2a-terminology-mapping.md` — terminology mapping spec.
- `specs/en/admin-requirements.md` — admin requirements spec.
- `specs/en/api-protocol.md` — API protocol spec.
- `specs/en/web-requirements.md` — web requirements spec.

## Templates

- `templates/general/agent-prd-template.md` — agent PRD template.
- `templates/general/development-plan-template.md` — development plan template.
- `templates/external-agents/template/README.md` — external agent template package overview.
- `templates/openclaw/frontdesk/README.md` — OpenClaw frontdesk template root.
- `templates/openclaw/frontdesk/AGENTS.md` — frontdesk agent instructions template.
- `templates/openclaw/frontdesk/BOOTSTRAP.md` — frontdesk bootstrap template.
- `templates/openclaw/frontdesk/HEARTBEAT.md` — frontdesk heartbeat template.
- `templates/openclaw/frontdesk/IDENTITY.md` — frontdesk identity template.
- `templates/openclaw/frontdesk/MEMORY.md` — frontdesk memory template.
- `templates/openclaw/frontdesk/SOUL.md` — frontdesk soul template.
- `templates/openclaw/frontdesk/TOOLS.md` — frontdesk tools template.
- `templates/openclaw/frontdesk/USER.md` — frontdesk user context template.
