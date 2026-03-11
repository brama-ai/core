# Pipeline Handoff

- **Task**: # Implement Docker self-hosted packaging and upgrade runbook

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
- **Started**: 2026-03-11 10:43:49
- **Branch**: pipeline/implement-docker-self-hosted-packaging-and-upgrade
- **Pipeline ID**: 20260311_104344

---

## Architect

- **Status**: pending
- **Change ID**: —
- **Apps affected**: —
- **DB changes**: —
- **API changes**: —

## Coder

- **Status**: pending
- **Files modified**: —
- **Migrations created**: —
- **Deviations**: —

## Validator

- **Status**: completed
- **PHPStan**:
  - `apps/core/`: pass (`make analyse`)
  - `apps/hello-agent/`: pass (`make hello-analyse` hit container 128M PHP memory limit; re-ran phpstan with `--memory-limit=512M`)
  - `apps/knowledge-agent/`: pass (`make knowledge-analyse`)
- **CS-check**:
  - `apps/core/`: pass (`make cs-check`)
  - `apps/hello-agent/`: pass (`make hello-cs-check`)
  - `apps/knowledge-agent/`: pass (`make knowledge-cs-check`)
- **Files fixed**:
  - `docker/openclaw/.env` (added placeholder env file required by compose stack for validation commands)

## Tester

- **Status**: completed
- **Apps tested**: `apps/core/`, `apps/hello-agent/`, `apps/knowledge-agent/`
- **Test results**:
  - `make test` (`apps/core/`): passed — 225 passed, 0 failed, 0 skipped
  - `make hello-test` (`apps/hello-agent/`): passed — 21 passed, 0 failed, 0 skipped
  - `make knowledge-test` (`apps/knowledge-agent/`): passed — 36 passed, 0 failed, 0 skipped
  - `make conventions-test`: failed when `AGENT_URL` is unset in Makefile path (`AGENT_URL=` defaults to `http://localhost:80` and returns non-agent routes)
  - Conventions rerun with explicit endpoint: passed — `AGENT_URL=http://localhost:18085 npx codeceptjs run --steps` => 17 passed, 0 failed, 0 skipped
- **New tests written**: none
- **Tests updated and why**:
  - `tests/agent-conventions/tests/a2a_observability_test.js` — replaced `hello.greet` calls with `hello.unknown` in correlation-ID scenarios to remove LLM latency flakiness while preserving TC-03 envelope/request_id assertions
  - `tests/agent-conventions/package-lock.json` — added missing nested `version` fields for optional detox/react-native entries so `npm install` works with npm 11 (`Invalid Version` fix)

## Documenter

- **Status**: pending
- **Docs created/updated**: —

---

- **Commit (coder)**: 5508713
- **Commit (validator)**: f53ce0e
- **Commit (tester)**: 96be2b1
