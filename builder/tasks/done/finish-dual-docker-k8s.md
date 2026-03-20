<!-- batch: 20260319_152205 | status: pass | duration: 2400s | branch: pipeline/finish-change-add-dual-docker-kubernetes-deploymen -->
<!-- priority: 2 -->
# Finish change: add-dual-docker-kubernetes-deployment

Завершити 5 залишених задач з OpenSpec change add-dual-docker-kubernetes-deployment.

## OpenSpec

- Proposal: openspec/changes/add-dual-docker-kubernetes-deployment/proposal.md
- Tasks: openspec/changes/add-dual-docker-kubernetes-deployment/tasks.md
- Specs: openspec/changes/add-dual-docker-kubernetes-deployment/specs/

## Context

Залишилось 5 задач — hardening та validation:
1. 4.1 Ensure every HTTP service exposes production-ready health and readiness behavior
2. 4.2 Ensure long-running workers and schedulers have safe restart and shutdown behavior
3. 4.3 Remove hidden Compose-only assumptions from config and service wiring
4. 6.1 Validate with OpenSpec strict validation
5. 6.2 Verify at least one E2E smoke path for Docker and one install validation for K8s

Read tasks.md for full details. Focus on code-level changes (4.1-4.3), then validation.

## Validation

- PHPStan passes
- CS-Fixer passes
- All tests pass
- tasks.md items marked [x]
