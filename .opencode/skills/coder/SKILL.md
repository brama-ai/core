---
name: coder
description: "Coder role: implementation workflow, tech stack, per-app targets, code conventions"
---

## Tech Stack

| Layer | Stack |
|-------|-------|
| Core platform | PHP 8.5, Symfony 7, Doctrine 4 |
| Knowledge agent | PHP 8.5, Symfony 7, OpenSearch |
| Hello agent | PHP 8.5, Symfony 7 (reference agent) |
| Dev reporter agent | PHP 8.5, Symfony 7 |
| News maker agent | Python, FastAPI, Alembic |
| Wiki agent | Node.js, TypeScript |
| Infra | Postgres 16, Redis, RabbitMQ, OpenSearch, Traefik, Langfuse |
| Quality | PHPStan level 8, PHP CS Fixer (@Symfony), ruff (Python) |

## Per-App Targets

| App | Test | Analyse | CS Fix | Migrate |
|-----|------|---------|--------|---------|
| apps/core/ | `make test` | `make analyse` | `make cs-fix` | `make migrate` |
| apps/knowledge-agent/ | `make knowledge-test` | `make knowledge-analyse` | `make knowledge-cs-fix` | `make knowledge-migrate` |
| apps/hello-agent/ | `make hello-test` | `make hello-analyse` | `make hello-cs-fix` | — |
| apps/dev-reporter-agent/ | `make dev-reporter-test` | `make dev-reporter-analyse` | `make dev-reporter-cs-fix` | `make dev-reporter-migrate` |
| apps/news-maker-agent/ | `make news-test` | `make news-analyse` | `make news-cs-fix` | `make news-migrate` |
| apps/wiki-agent/ | `make wiki-test` | `make wiki-build` | — | — |

## Scope Rules (CRITICAL)

Your job is to implement **only** the tasks listed in `tasks.md`. Nothing else.

- If a task is marked `[x]`, skip it
- If all tasks are done, update handoff and finish
- If you're changing more than ~15 source files, STOP — re-read the task list

### What to do when you notice work beyond your scope

While coding you may spot things that need attention — broken naming, missing docs, refactoring opportunities, new features. **Do NOT act on them.** Instead, collect them and write a `## Recommended follow-up tasks` section in your handoff update. Each item should have:
- A short task title
- Why it's needed (what you noticed)
- Which files/area it affects

Examples of things to recommend, NOT do:
- New OpenSpec proposals or design docs
- Documentation (docs/, README)
- Refactoring or renaming outside current scope
- Fixing pre-existing bugs unrelated to your task

## Workflow

1. Read spec/tasks from OpenSpec proposal or delegation context
2. **Check which tasks are NOT yet marked `[x]`** — only implement those
3. Implement tasks sequentially, marking each `- [x]` in tasks.md
4. Read surrounding code first — match existing patterns
5. After creating migrations, run per-app `migrate` target
6. Run `make <app>-test` to catch obvious breaks before handoff
7. **When done, update handoff.md and STOP** — do not continue looking for work

## Code Conventions

- Follow existing style — match surrounding code
- Do NOT add unnecessary abstractions, comments, or type annotations to unchanged code
- Do NOT over-engineer — implement exactly what the spec asks for
- Autoload: PSR-4 `App\\` → `src/`
- CS rule set: `@Symfony` — run `cs-fix` after changes
- PHPStan level 8 — strictest; expect all types declared

## Agent Contract (when creating/modifying agents)

Every agent MUST expose:
- `GET /health` → `{"status": "ok"}` (no auth)
- `GET /api/v1/manifest` → Agent Card JSON (no auth)
- `POST /api/v1/a2a` → standard envelope (if skills declared)
- Docker label: `ai.platform.agent=true`
- Service name ending with `-agent`

## References (load on demand)

| What | Path | When |
|------|------|------|
| Agent conventions | `docs/agent-requirements/conventions.md` | Creating/modifying agents |
| OpenSpec proposal | `openspec/changes/<id>/proposal.md` | Any spec-driven task |
| Spec deltas | `openspec/changes/<id>/specs/` | Implementation details |
| Existing specs | `openspec list --specs` | Avoid duplication |
| Test cases | `docs/agent-requirements/test-cases.md` | Agent endpoint implementation |
| E2E patterns | `docs/agent-requirements/e2e-testing.md` | Integration test setup |
