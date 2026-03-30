# Tasks: Archive Completed OpenSpec Changes

## 1. Archive Completed Changes

Move each directory from `openspec/changes/<id>/` to `openspec/changes/archive/2026-03-30-<id>/`.

- [ ] 1.1 Archive `add-k3s-storage-architecture` → `archive/2026-03-30-add-k3s-storage-architecture/`
- [ ] 1.2 Archive `add-kubernetes-agent-discovery` → `archive/2026-03-30-add-kubernetes-agent-discovery/`
- [ ] 1.3 Archive `complete-admin-agent-registry` → `archive/2026-03-30-complete-admin-agent-registry/`
- [ ] 1.4 Archive `fix-psr4-multitenant-e2e-boot` → `archive/2026-03-30-fix-psr4-multitenant-e2e-boot/`
- [ ] 1.5 Archive `fix-remaining-e2e-failures` → `archive/2026-03-30-fix-remaining-e2e-failures/`
- [ ] 1.6 Archive `refactor-foundry-runtime-entrypoint` → `archive/2026-03-30-refactor-foundry-runtime-entrypoint/`
- [ ] 1.7 Archive `validate-k3s-local-runtime` → `archive/2026-03-30-validate-k3s-local-runtime/`
- [ ] 1.8 Archive `refactor-task-centric-pipeline-state` → `archive/2026-03-30-refactor-task-centric-pipeline-state/`

## 2. Update ROADMAP.md — Move Archived Items to Completed

- [ ] 2.1 Add the following to the "Completed" section under Q1 2025:
  - `add-k3s-storage-architecture` — K3s Storage Architecture
  - `add-kubernetes-agent-discovery` — Kubernetes Agent Discovery
  - `complete-admin-agent-registry` — Admin Agent Registry Completion
  - `fix-psr4-multitenant-e2e-boot` — PSR-4 Multi-Tenant E2E Boot Fix
  - `fix-remaining-e2e-failures` — Remaining E2E Failure Fixes
  - `refactor-foundry-runtime-entrypoint` — Foundry Runtime Entrypoint Refactor
  - `validate-k3s-local-runtime` — K3s Local Runtime Validation
  - `refactor-task-centric-pipeline-state` — Task-Centric Pipeline State Refactor
- [ ] 2.2 Remove the above items from the "In Progress" section (where applicable)

## 3. Update ROADMAP.md — Correct Task Counts for Active Changes

Update task counts in ROADMAP.md to match actual `tasks.md` progress:

- [ ] 3.1 `improve-development-workflow`: update from `8/10` to `20/22` (was `24/26` per openspec, but 20 checked + 2 unchecked in tasks.md)
- [ ] 3.2 `add-admin-agent-registry`: remove from In Progress (already archived as `2026-03-21-add-admin-agent-registry` and completed via `complete-admin-agent-registry`)
- [ ] 3.3 `add-knowledge-base-agent`: verify current count (listed as `46/79`)
- [ ] 3.4 `add-dev-reporter-agent`: verify current count (listed as `33/37`)
- [ ] 3.5 `add-a2a-trace-sequence-visualization`: verify current count (listed as `19/23`)
- [ ] 3.6 `add-openclaw-agent-discovery`: verify current count (listed as `19/30`)
- [ ] 3.7 `add-dual-docker-kubernetes-deployment`: verify current count (listed as `18/23`)
- [ ] 3.8 `add-telegram-bot-integration`: verify current count (listed as `0/181`)
- [ ] 3.9 `add-tenant-management`: update from `0/15` to `0/13` (actual tasks.md count)
- [ ] 3.10 `async-scheduler-dispatch`: remove from In Progress (already archived as `2026-03-27-async-scheduler-dispatch`)
- [ ] 3.11 `migrate-to-k3s-hetzner`: update from `0/42` to `22/42` (actual tasks.md progress)

## 4. Post-Archive Validation

- [ ] 4.1 Run `openspec validate --strict` — confirm no broken references from archived changes
- [ ] 4.2 Run `openspec list` — confirm only active (non-archived) changes appear
- [ ] 4.3 Verify `openspec list` output matches ROADMAP.md active items

## 5. Documentation

- [ ] 5.1 Update ROADMAP.md "Last updated" date to current date
- [ ] 5.2 Verify ROADMAP.md formatting is consistent after edits
