---
name: OpenSpec: Apply
description: Implement an approved OpenSpec change and keep tasks in sync.
category: OpenSpec
tags: [openspec, apply]
---
<!-- OPENSPEC:START -->
**Guardrails**
- Favor straightforward, minimal implementations first and add complexity only when it is requested or clearly required.
- Keep changes tightly scoped to the requested outcome.
- Refer to `openspec/AGENTS.md` (located inside the `openspec/` directory—run `ls openspec` or `openspec update` if you don't see it) if you need additional OpenSpec conventions or clarifications.

**Steps**
Track these steps as TODOs and complete them one by one.
1. Read `changes/<id>/proposal.md`, `design.md` (if present), and `tasks.md` to confirm scope and acceptance criteria.
2. Work through tasks sequentially, keeping edits minimal and focused on the requested change.
3. Write tests at all applicable levels:
   - **Unit tests** (`apps/core/tests/Unit/`) for business logic, entities, services.
   - **Functional tests** (`apps/core/tests/Functional/`) for user flows and API endpoints.
   - **E2E tests** (`tests/e2e/tests/admin/`) if the change adds or modifies admin pages or routes.
     - Create a Page Object in `tests/e2e/support/pages/` for new admin sections.
     - Register the Page Object in `tests/e2e/codecept.conf.js` under `include`.
     - Follow existing test patterns (see `tests/e2e/tests/admin/scheduler_test.js` as reference).
4. Run quality checks before marking done:
   - `phpstan analyse` — zero new errors at level 8
   - `codecept run Unit` — all unit tests pass
   - `codecept run Functional` — all functional tests pass
   - `make e2e` — Playwright E2E passes (if Docker stack is available)
5. Confirm completion before updating statuses—make sure every item in `tasks.md` is finished.
6. Update the checklist after all work is done so each task is marked `- [x]` and reflects reality.
7. Reference `openspec list` or `openspec show <item>` when additional context is required.

**Reference**
- Use `openspec show <id> --json --deltas-only` if you need additional context from the proposal while implementing.
<!-- OPENSPEC:END -->
