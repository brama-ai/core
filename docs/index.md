# Brama Agent Platform — Agent Index

## Deployed Agents

| Agent | Stack | Source | Status | Manifest | PRD |
|-------|-------|--------|--------|----------|-----|
| [hello-agent](../apps/hello-agent/) | PHP 8.5 / Symfony 7 | in-repo + [external pilot](../projects/hello-agent/) | Active | `/api/v1/manifest` | [EN](agents/en/hello-agent.md) · [UA](agents/ua/hello-agent.md) |
| [knowledge-agent](../apps/knowledge-agent/) | PHP 8.5 / Symfony 7 | in-repo | Active | `/api/v1/manifest` | — |
| [news-maker-agent](../apps/news-maker-agent/) | Python / FastAPI | in-repo | Active | `/api/v1/manifest` | — |
| [dev-reporter-agent](../apps/dev-reporter-agent/) | PHP 8.5 / Symfony 7 | in-repo | Active | `/api/v1/manifest` | [EN](agents/en/dev-reporter-agent.md) · [UA](agents/ua/dev-reporter-agent.md) |
| [wiki-agent](../apps/wiki-agent/) | TypeScript / Node.js | in-repo | Active | `/api/v1/manifest` | [EN](agents/en/wiki-agent.md) · [UA](agents/ua/wiki-agent.md) |

## Planned Agents (PRD only)

| Agent | PRD |
|-------|-----|
| anti-fraud-signals | [EN](agents/en/anti-fraud-signals-prd.md) · [UA](agents/ua/anti-fraud-signals-prd.md) |
| knowledge-extractor | [EN](agents/en/knowledge-extractor-prd.md) · [UA](agents/ua/knowledge-extractor-prd.md) |
| locations-catalog | [EN](agents/en/locations-catalog-prd.md) · [UA](agents/ua/locations-catalog-prd.md) |
| news-digest | [EN](agents/en/news-digest-prd.md) · [UA](agents/ua/news-digest-prd.md) |

## Platform Core

| Component | Path | Description |
|-----------|------|-------------|
| [core](../apps/core/) | `apps/core/` | Admin UI, A2A gateway, agent registry, log viewer |

## Key Links

- [Agent conventions](agent-requirements/conventions.md)
- [Multi-agent pipeline](features/pipeline/en/pipeline.md)
- [Dashboard platform metrics](features/dashboard-metrics/en/dashboard-metrics.md)
- [i18n Locale Cookie](features/i18n-locale/en/i18n-locale.md)
- [Agent Card schema](../apps/core/config/agent-card.schema.json)
- [Local dev guide](setup/local-dev/en/local-dev.md)
- [Production deployment guide](guides/deployment/en/deployment.md)
- [Deployment overview](guides/deployment/en/deployment-overview.md)
- [A2A terminology mapping (EN)](specs/en/a2a-terminology-mapping.md)
- [External agent workspace](guides/external-agents/en/external-agent-workspace.md)
- [External agent operator onboarding](guides/external-agents/en/operator-onboarding.md)
- [External agent migration playbook](guides/external-agents/en/migration-playbook.md)
- [Pilot agent selection](guides/external-agents/en/pilot-agent-selection.md)
- [Oh My OpenCode (OmO) integration](guides/oh-my-opencode/en/oh-my-opencode.md)
- [Oh My OpenCode — Quick Start](guides/oh-my-opencode/en/quickstart.md)

Brama Agent Platform is the public product name for this repository. Legacy runtime identifiers may
still use `ai-community-platform` until the infrastructure rename is handled separately.
