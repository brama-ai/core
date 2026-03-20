<!-- batch: 20260311_104341 | status: pass | duration: 981s | branch: pipeline/implement-kubernetes-packaging-skeleton-and-operat -->
# Implement Kubernetes packaging skeleton and operator runbooks

Implement the Kubernetes-focused phase of the approved OpenSpec change
`add-dual-docker-kubernetes-deployment`.

## Goal

Create the first official Kubernetes packaging path for the platform, with Helm-oriented structure,
deployment contract, and operator runbooks.

## Scope

- Introduce the initial Kubernetes packaging skeleton
- Prefer Helm as the operator-facing interface
- Define the target configuration model for:
  - image tags
  - ingress
  - secrets
  - external managed dependencies
  - persistence
  - probes
  - migration jobs/hooks
- Add Kubernetes operator docs for:
  - install
  - upgrade
  - rollback
  - troubleshooting
- Base the implementation on the draft Kubernetes runbook and target-state assumptions

## OpenSpec References

- `openspec/changes/add-dual-docker-kubernetes-deployment/proposal.md`
- `openspec/changes/add-dual-docker-kubernetes-deployment/tasks.md`
- `openspec/changes/add-dual-docker-kubernetes-deployment/design.md`
- `openspec/changes/add-dual-docker-kubernetes-deployment/specs/kubernetes-packaging/spec.md`
- `openspec/changes/add-dual-docker-kubernetes-deployment/runbooks/kubernetes-upgrade-runbook.md`

## Relevant Repo Context

- current Docker deployment docs and compose topology
- `compose.yaml`
- `compose.core.yaml`
- `compose.langfuse.yaml`
- `compose.openclaw.yaml`
- `docs/guides/deployment/`
- `docs/product/ua/architecture-overview.md`

## Acceptance Criteria

- The repo contains an initial official Kubernetes packaging entrypoint
- The target chart/manifests define a clear operator contract, even if the first cut is minimal
- Kubernetes docs describe install and upgrade flow in a way consistent with the proposal
- The packaging supports explicit handling of migrations, probes, secrets, and ingress
- The docs clearly distinguish what is already implemented versus planned future hardening

## Constraints

- Do not rely on compose-to-k8s auto-conversion as the final operator interface
- Do not hide migration behavior inside undocumented startup side effects
- Keep the first implementation realistic and incremental

## Validation

- Run relevant tests/checks for changed code and docs
- Run `openspec validate add-dual-docker-kubernetes-deployment --strict`

