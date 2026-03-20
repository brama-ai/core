# Documentation Index

Agent-facing memory index for `AI Community Platform`. Always load this file first.

## Product

- `docs/product/en/brainstorm.md` — raw brainstorming and first product framing
- `docs/product/en/platform-mvp-prd.md` — PRD for the platform MVP
- `docs/product/en/architecture-overview.md` — baseline architecture, modularity model, and MVP boundaries
- `docs/product/en/core-agent-openclaw.md` — OpenClaw as a runtime for the core-agent, not the core-platform

## Interface Specs

- `docs/specs/en/README.md` — index of interface and protocol specifications
- `docs/specs/en/web-requirements.md` — requirements for the public web layer
- `docs/specs/en/admin-requirements.md` — requirements for the admin layer
- `docs/specs/en/api-protocol.md` — API contract and mandatory OpenAPI documentation
- `docs/specs/en/a2a-protocol.md` — internal agent-to-agent protocol

## Agent PRDs

- `docs/agents/en/hello-agent.md` — Hello Agent
- `docs/agents/en/dev-reporter-agent.md` — Dev Reporter Agent: pipeline run ingestion, Telegram notifications, admin panel
- `docs/agents/en/knowledge-extractor-prd.md` — PRD for Community Wiki / Knowledge Extractor
- `docs/agents/en/locations-catalog-prd.md` — PRD for the locations, services, and trusted contacts catalog
- `docs/agents/en/news-digest-prd.md` — PRD for News Digest
- `docs/agents/en/anti-fraud-signals-prd.md` — PRD for Anti-fraud Signals

## Agent Requirements

- `docs/agent-requirements/conventions.md` — baseline technical conventions for all agents
- `docs/agent-requirements/test-cases.md` — convention compliance test suite
- `docs/agent-requirements/e2e-testing.md` — end-to-end testing guide
- `docs/agent-requirements/observability-requirements.md` — tracing/logging requirements for multi-agent and multi-stack flows

## Architecture Decisions

- `docs/decisions/adr_0002_openclaw_role.md` — ADR for the OpenClaw positioning decision

## Features

- `docs/features/overview/ua/README.md` — feature documentation index (UA)
- `docs/features/litellm/ua/litellm.md` — LiteLLM gateway, credentials, and operational checks (UA)
- `docs/features/litellm/en/litellm.md` — LiteLLM gateway, credentials, and operational checks (EN)

## Delivery Plans

- `docs/plans/platform-mvp-development-plan.md` — overall platform delivery plan
- `docs/plans/knowledge-extractor-development-plan.md` — plan for Knowledge Extractor
- `docs/plans/locations-catalog-development-plan.md` — plan for Locations Catalog
- `docs/plans/news-digest-development-plan.md` — plan for News Digest
- `docs/plans/anti-fraud-signals-development-plan.md` — plan for Anti-fraud Signals
- `docs/plans/openclaw-observability-rollout-plan.md` — rollout plan for end-to-end observability via OpenClaw
- `docs/plans/telegram-openclaw-integration-plan.md` — Telegram-OpenClaw integration plan

## Frameworks

- `docs/neuron-ai/reference/index.md` — Neuron AI framework documentation for building AI agents

### Neuron AI Reference Projects

- [Deep Research Agent](https://github.com/neuron-core/deep-research-agent) — Reference implementation for multi-step research workflows using neuron-ai
  - Key files: `src/Workflow/ResearchWorkflow.php`, `src/Node/`, `config/services.yaml`
  - Usage patterns: Multi-step workflows, external API integration, structured output
- [Travel Planner Agent](https://github.com/neuron-core/travel-planner-agent) — Reference implementation for complex planning workflows
  - Key files: `src/Workflow/PlanningWorkflow.php`, `src/Service/`, `src/Entity/`
  - Usage patterns: State management, conditional branching, data persistence

## Templates

- `docs/templates/general/agent-prd-template.md` — template for new agent PRDs
- `docs/templates/general/development-plan-template.md` — template for development plans

## Local Runtime

- `docs/setup/local-dev/en/local-dev.md` — local Docker Compose development environment (includes OpenClaw + Telegram setup)

## Recommended Build Order

1. Core platform
2. Knowledge Extractor
3. Locations Catalog
4. News Digest
5. Anti-fraud Signals
