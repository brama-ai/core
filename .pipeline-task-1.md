# Implement Docker self-hosted packaging and upgrade runbook

Implement the Docker-focused phase of the approved OpenSpec change
`add-dual-docker-kubernetes-deployment`.

## Goal

Turn the current Compose-based deployment into a supported operator-facing Docker offering with a
clear install, upgrade, rollback, and verification workflow.

## Scope

- Refine the existing compose topology into an operator-facing Docker packaging model
- Standardize deployment inputs:
  - env vars
  - secrets
  - public URLs
  - overrides
  - optional agent/add-on fragments
- Improve or document the supported Docker upgrade flow based on the draft runbook
- Add Docker-specific operator docs:
  - install
  - upgrade
  - rollback
  - backup/restore
  - troubleshooting
- Align the implementation with the current `Makefile` workflow where possible

## OpenSpec References

- `openspec/changes/add-dual-docker-kubernetes-deployment/proposal.md`
- `openspec/changes/add-dual-docker-kubernetes-deployment/tasks.md`
- `openspec/changes/add-dual-docker-kubernetes-deployment/design.md`
- `openspec/changes/add-dual-docker-kubernetes-deployment/specs/self-hosted-deployment/spec.md`
- `openspec/changes/add-dual-docker-kubernetes-deployment/runbooks/docker-upgrade-runbook.md`

## Relevant Repo Context

- `Makefile`
- `compose.yaml`
- `compose.core.yaml`
- `compose.langfuse.yaml`
- `compose.openclaw.yaml`
- `compose.slides.yaml`
- `docs/guides/deployment/ua/deployment.md`
- `docs/guides/deployment/en/deployment.md`
- `docs/local-dev.md`

## Acceptance Criteria

- Docker deployment is documented as an explicit supported mode, not just an emergent dev setup
- Operator docs include a clear upgrade sequence and rollback path
- Required migrations and verification steps are documented and consistent with actual repo commands
- The supported topology and override points are explicit
- Docker deployment docs stay aligned with the real compose and Makefile behavior

## Constraints

- Do not invent Kubernetes-only assumptions in the Docker path
- Do not break local development while hardening the self-hosted Docker story
- Prefer explicit docs and stable commands over clever automation

## Validation

- Run relevant tests/checks for changed code and docs
- Run `openspec validate add-dual-docker-kubernetes-deployment --strict`

