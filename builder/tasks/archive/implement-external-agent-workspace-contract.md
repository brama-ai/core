<!-- batch: 20260311_104341 | status: pass | duration: 809s | branch: pipeline/implement-external-agent-workspace-contract -->
# Implement external agent workspace contract

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

