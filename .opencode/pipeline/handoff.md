# Pipeline Handoff

- **Task**: # Implement pilot agent externalization

Implement the approved OpenSpec change `refactor-agents-into-external-repositories` phase 2 using
one pilot agent.

## Goal

Prove that the external-agent repository model works in practice by migrating one real agent out of
the monorepo workflow and documenting the migration path.

## Scope

- Choose one current agent as the pilot candidate based on coupling and risk
- Extract or mirror that agent into the external-repository workflow defined by the platform
- Make the platform able to run the pilot agent from `projects/<agent-name>/`
- Document the migration playbook for future agents
- Keep the runtime contract stable:
  - service name
  - manifest endpoint
  - health endpoint
  - admin URL shape
  - A2A endpoint path

## OpenSpec References

- `openspec/changes/refactor-agents-into-external-repositories/proposal.md`
- `openspec/changes/refactor-agents-into-external-repositories/tasks.md`
- `openspec/changes/refactor-agents-into-external-repositories/design.md`

## Relevant Repo Context

- existing agent implementations under `apps/`
- `compose.agent-hello.yaml`
- `compose.agent-knowledge.yaml`
- `compose.agent-dev-reporter.yaml`
- `compose.agent-dev.yaml`
- `compose.agent-news-maker.yaml`
- `compose.agent-wiki.yaml`
- `docs/index.md`
- `docs/agents/`

## Acceptance Criteria

- One pilot agent is selected and the choice is justified in docs
- The platform can run the pilot from the external workspace contract rather than only from `apps/`
- A migration playbook exists for moving future agents to the same model
- Compatibility checks, manifest discovery, and runtime verification are documented and exercised
- The transition rules are explicit, especially around naming, URLs, and migrations

## Constraints

- Do not bulk-migrate all agents in one pass
- Do not introduce a second incompatible runtime contract for external agents
- Prefer the least coupled pilot agent first
- Keep docs and platform tooling aligned

## Validation

- Run relevant tests/checks for the chosen pilot workflow
- Run `openspec validate refactor-agents-into-external-repositories --strict`
- **Started**: 2026-03-11 11:06:31
- **Branch**: pipeline/implement-pilot-agent-externalization
- **Pipeline ID**: 20260311_110629

---

## Architect

- **Status**: pending
- **Change ID**: —
- **Apps affected**: —
- **DB changes**: —
- **API changes**: —

## Coder

- **Status**: done
- **Files modified**:
  - `Makefile` — added `EXTERNAL_AGENTS_FILE`/`EXTERNAL_AGENTS_COMPOSE` variables, included `compose.external-agents.yaml` in `COMPOSE_FILES`, added `.PHONY` entries and help text for 4 new targets, added `external-agent-clone`, `external-agent-up`, `external-agent-down`, `external-agent-list` targets
  - `compose.external-agents.yaml` — new file: platform-level compose loader for external agent fragments; includes `projects/hello-agent/compose.fragment.yaml`
  - `projects/.gitignore` — new file: ignores `*/src/` (external git clones) while tracking compose fragments and READMEs
  - `projects/hello-agent/compose.fragment.yaml` — new file: pilot external agent compose fragment
  - `projects/hello-agent/README.md` — new file: pilot workspace documentation
  - `projects/hello-agent/src` — new symlink → `../../apps/hello-agent` (pilot in-repo source link)
  - `docs/index.md` — added Source column to agent table, added 4 external-agent guide links
  - `docs/agent-requirements/conventions.md` — added Section 9: External Agent Repositories (compose fragment contract, workspace convention, operator workflow, compatibility rules)
  - `docs/guides/external-agents/en/external-agent-workspace.md` — new file: developer-facing workspace convention doc
  - `docs/guides/external-agents/en/operator-onboarding.md` — new file: operator onboarding guide (EN)
  - `docs/guides/external-agents/en/migration-playbook.md` — new file: step-by-step migration playbook
  - `docs/guides/external-agents/en/pilot-agent-selection.md` — new file: pilot selection rationale and agent inventory
  - `docs/guides/external-agents/en/template/compose.fragment.yaml` — new file: compose fragment template for new external agents
  - `docs/guides/external-agents/en/template/README.md` — new file: external agent repository template README
  - `docs/guides/external-agents/ua/operator-onboarding.md` — new file: operator onboarding guide (UA mirror)
  - `openspec/changes/refactor-agents-into-external-repositories/tasks.md` — all tasks marked `[x]`
- **Migrations created**: none (hello-agent has no database)
- **Deviations**:
  - The pilot `projects/hello-agent/src` is a symlink to `apps/hello-agent` rather than a real git clone. This is intentional: the pilot proves the external workspace contract works without duplicating source. A real external agent would clone its own repository here. This is documented in `projects/hello-agent/README.md`.
  - `compose.external-agents.yaml` uses Docker Compose `include:` syntax (Compose v2.20+) rather than a shell script. This is cleaner and aligns with the existing `compose.override.yaml` pattern. The `required: false` flag ensures the platform stack starts normally on machines without external agent checkouts.

## Validator

- **Status**: done
- **Apps validated**: `apps/hello-agent/` (pilot app referenced by this change)
- **PHPStan**:
  - `apps/hello-agent/`: pass (`make hello-analyse`)
- **CS-check**:
  - `apps/hello-agent/`: pass (`make hello-cs-check`)
- **Files fixed**: none

## Tester

- **Status**: pending
- **Test results**: —
- **New tests written**: —

## Documenter

- **Status**: pending
- **Docs created/updated**: —

---

- **Commit (coder)**: 497d780
