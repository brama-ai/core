# Pipeline Handoff

- **Task**: # Implement external agent workspace contract

Implement the approved OpenSpec change `refactor-agents-into-external-repositories` phase 1.

## Goal

Create the first real platform support for externally checked out agents so the repo no longer
assumes that every production agent must live under `apps/`.

## Scope

- Define and implement the canonical external checkout convention: `projects/<agent-name>/`
- Add compose loading support for external agent fragments without hardcoding each agent into the
  base platform compose files
- Add Makefile targets or scripts for external agent workflows:
  - detect/list external agent compose fragments
  - start/update a named external agent
  - stop a named external agent
- Ensure the approach remains compatible with existing agent discovery and manifest conventions
- Add documentation for the external workspace contract and operator onboarding flow

## OpenSpec References

- `openspec/changes/refactor-agents-into-external-repositories/proposal.md`
- `openspec/changes/refactor-agents-into-external-repositories/tasks.md`
- `openspec/changes/refactor-agents-into-external-repositories/design.md`
- `openspec/changes/refactor-agents-into-external-repositories/specs/external-agent-workspace/spec.md`
- `openspec/changes/refactor-agents-into-external-repositories/specs/external-agent-onboarding/spec.md`

## Relevant Repo Context

- `Makefile`
- `compose.yaml`
- `compose.core.yaml`
- `compose.agent-*.yaml`
- `docs/guides/deployment/ua/deployment.md`
- `docs/guides/deployment/en/deployment.md`
- `docs/agent-requirements/`
- `openspec/changes/refactor-agent-discovery/`

## Acceptance Criteria

- Operators have one documented and supported path to connect an external agent checkout from
  `projects/<agent-name>/`
- Platform tooling no longer requires editing top-level compose files per external agent install
- The implementation preserves the existing manifest/health/discovery contract
- The docs explain:
  - repository checkout convention
  - compose fragment convention
  - env/secrets expectations
  - healthcheck and labels requirements
  - verification and detach flow
- Any added script or Make target uses the current repo conventions and is documented

## Constraints

- Do not implement a remote marketplace or auto-cloning from admin UI
- Do not break the current in-repo agent workflow
- Keep the change incremental and compatible with current Docker development flow
- Follow OpenSpec and update docs as part of the implementation

## Validation

- Run relevant tests/checks for changed code
- Run `openspec validate refactor-agents-into-external-repositories --strict`
- **Started**: 2026-03-11 10:43:50
- **Branch**: pipeline/implement-external-agent-workspace-contract
- **Pipeline ID**: 20260311_104344

---

## Architect

- **Status**: pending
- **Change ID**: —
- **Apps affected**: —
- **DB changes**: —
- **API changes**: —

## Coder

- **Status**: done
- **Files created**:
  - `compose.external-agents.yaml` — documents the external agent fragment loading convention
  - `compose.fragments/.gitkeep` — tracks the fragments directory in git
  - `compose.fragments/example-agent.yaml.template` — reference compose fragment template for agent authors
  - `projects/.gitkeep` — tracks the external agent checkouts directory in git
  - `scripts/external-agent.sh` — script implementing list/up/down/clone commands
  - `docs/guides/external-agents/en/onboarding.md` — operator onboarding guide (English)
  - `docs/guides/external-agents/ua/onboarding.md` — operator onboarding guide (Ukrainian, canonical)
  - `docs/guides/external-agents/en/repository-structure.md` — developer guide for external agent repo structure
  - `docs/guides/external-agents/en/migration-playbook.md` — playbook for migrating bundled agents to external repos
- **Files modified**:
  - `Makefile` — added `EXTERNAL_AGENT_FILES` variable, included fragments in `COMPOSE_FILES`, added `external-agent-list/up/down/clone` targets
  - `.gitignore` — added `projects/` and `compose.fragments/*.yaml` (with exceptions for tracked files)
  - `docs/agent-requirements/conventions.md` — added external agent checklist (section 8) and workspace convention (section 9)
  - `docs/guides/deployment/en/deployment.md` — added External Agents section
  - `docs/guides/deployment/ua/deployment.md` — added Зовнішні агенти section
  - `openspec/changes/refactor-agents-into-external-repositories/tasks.md` — marked all tasks `[x]`
- **Migrations created**: none (no PHP/DB changes)
- **Deviations**:
  - `compose.external-agents.yaml` is a documentation-only file (no services). The actual loading mechanism is the `EXTERNAL_AGENT_FILES := $(wildcard compose.fragments/*.yaml)` variable in the Makefile, which auto-discovers all fragments without requiring operators to edit any platform file. This is simpler and more robust than a single aggregator compose file.
  - Pilot agent (`hello-agent`) migration is documented in the playbook but not executed — the spec calls for documenting the playbook, not performing the actual extraction in this phase.

## Validator

- **Status**: done
- **PHPStan**:
  - No changed apps under `apps/`; validation scope contained no app analyse targets to run
- **CS-check**:
  - No changed apps under `apps/`; validation scope contained no app cs-check targets to run
- **Files fixed**:
  - `.opencode/pipeline/handoff.md`

## Tester

- **Status**: pending
- **Test results**: —
- **New tests written**: —

## Documenter

- **Status**: done
- **Docs created/updated**:
  - `docs/guides/external-agents/en/onboarding.md` — operator onboarding guide (EN)
  - `docs/guides/external-agents/ua/onboarding.md` — operator onboarding guide (UA, canonical)
  - `docs/guides/external-agents/en/repository-structure.md` — external agent repo structure guide
  - `docs/guides/external-agents/en/migration-playbook.md` — migration playbook
  - `docs/guides/deployment/en/deployment.md` — added External Agents section
  - `docs/guides/deployment/ua/deployment.md` — added Зовнішні агенти section
  - `docs/agent-requirements/conventions.md` — added sections 8 & 9

## Auditor

- **Status**: done
- **Scope**: Platform-level audit (external agent workspace contract)
- **Results**: 7 PASS | 1 WARN | 0 FAIL
- **Verdict**: **PASS**
- **Findings**:
  - Makefile targets properly implemented with auto-discovery
  - Script executable with proper error handling
  - Complete bilingual documentation (EN/UA)
  - Gitignore correctly configured
  - Agent contract preserved for discovery/health
  - One WARN: Neuron tool not available for openspec validation (infrastructure constraint)

---

- **Commit (coder)**: 1551c93
- **Commit (validator)**: 563ef40
- **Commit (tester)**: 6b64a41
