---
name: architect
description: "Architect role: OpenSpec workflow, proposal scaffold, spec format, design conventions"
---

## OpenSpec Workflow

Every API/architecture change MUST follow:
1. **Spec first** — write spec in `openspec/changes/<id>/specs/`
2. **Validate** — `openspec validate <id> --strict`
3. **Implement** — code matches spec contract
4. **Test** — tests validate spec scenarios

## Proposal Structure

Scaffold under `openspec/changes/<change-id>/`:

```
openspec/changes/<change-id>/
├── proposal.md                 # What and why
├── design.md                   # Architecture reasoning, trade-offs, component interactions
├── tasks.md                    # Ordered work items with - [ ] checkboxes
└── specs/
    └── <capability>/spec.md    # Spec deltas with scenarios
```

## Spec Delta Format

```markdown
## ADDED Requirements

#### Scenario: User creates payment
Given: authenticated user with valid card
When: POST /payments with amount and currency
Then: returns 201 with payment_id and status "pending"

## MODIFIED Requirements

#### Scenario: Agent manifest includes storage
Given: agent with postgres storage declared
When: GET /api/v1/manifest
Then: response includes storage.postgres.startup_migration with enabled=true

## REMOVED Requirements

#### Scenario: Legacy XML endpoint
Removed: POST /api/v1/xml-import
Reason: Replaced by JSON-based import in add-json-import
```

## Design Doc Sections

A good `design.md` covers:
- **Problem** — what's broken or missing
- **Approach** — chosen solution with reasoning
- **Alternatives considered** — why rejected
- **Component interactions** — which apps/services are affected
- **Data model** — new tables, columns, indexes
- **API surface** — new/changed endpoints
- **Risks** — what could go wrong, mitigation

## Task Breakdown Rules

- Tasks must be small, verifiable, and ordered by dependency
- Each task should be completable in one agent session
- Include validation criteria: "PHPStan passes", "test X green"
- Group by app when multi-app change

## Platform Context

| Component | Stack | Key Files |
|-----------|-------|-----------|
| Core | PHP 8.5, Symfony 7 | `apps/core/src/`, `apps/core/config/` |
| Knowledge agent | PHP 8.5, Symfony 7 | `apps/knowledge-agent/src/` |
| News maker | Python, FastAPI | `apps/news-maker-agent/app/` |
| Wiki agent | Node.js, TS | `apps/wiki-agent/src/` |
| Infra | Postgres, Redis, RabbitMQ | `compose.yaml`, `compose.core.yaml` |

## Rules

- Choose a unique verb-led `change-id` (e.g., `add-streaming-support`)
- Run `openspec list` first — update existing proposal if one exists
- Run `openspec list --specs` for existing capability specs
- Reference existing specs to avoid duplication
- Never write implementation code — only specs and docs

## References (load on demand)

| What | Path | When |
|------|------|------|
| OpenSpec conventions | `openspec/AGENTS.md` | Always — primary reference |
| Project state | `openspec/project.md` | Understanding current scope |
| Existing specs | `openspec list --specs` | Avoiding duplication |
| Agent conventions | `docs/agent-requirements/conventions.md` | Agent-related changes |
| Existing proposals | `openspec/changes/` | Checking for conflicts |
