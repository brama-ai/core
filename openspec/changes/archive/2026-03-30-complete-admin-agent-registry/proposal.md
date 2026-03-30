# Change: Complete Admin Agent Registry

## Why

The admin agent registry feature is ~79% complete (30 of 38 original tasks done, per the [OpenSpec audit of 2026-03-05](../../docs/plans/openspec-audit-2026-03-05.md)). The core implementation — database schema, registry service, registration API, event bus/command router integration, health poller, and basic admin UI — is all in place and working. However, the feature lacks:

1. **E2E test coverage for key user journeys** — the agent install flow, config save persistence, violations modal, and admin iframe embedding have no E2E tests.
2. **Settings page i18n consistency** — `agent_settings.html.twig` uses hardcoded Ukrainian strings instead of translation keys, unlike the rest of the admin UI.
3. **Agent registry spec completeness** — the `agent-registry` spec's Purpose field is still "TBD" and does not document the admin management UI, agent list/detail views, or the install/enable/disable/delete lifecycle as user-facing requirements.
4. **Feature documentation** — no dedicated docs describe the admin agent registry for operators or developers.

This proposal closes the remaining gaps to bring the feature to 100% completion and make it archivable.

## What Changes

- **E2E tests**: Add Playwright E2E scenarios for agent install flow, config save + persistence verification, violations modal interaction, and admin iframe visibility
- **Functional tests**: Add Codeception functional test for agent delete/uninstall API endpoint
- **i18n fix**: Replace hardcoded Ukrainian strings in `agent_settings.html.twig` with `|trans` filter calls matching existing translation patterns
- **Spec update**: Update `agent-registry` spec Purpose and add requirements for the admin management UI (list view, detail/settings view, agent lifecycle actions)
- **Documentation**: Create developer-facing docs for the admin agent registry feature

## Impact

- Affected specs: `agent-registry` (MODIFIED: Purpose update + ADDED: admin UI requirements)
- Affected code: `brama-core/src/templates/admin/agent_settings.html.twig` (i18n), `brama-core/tests/e2e/tests/admin/` (new E2E tests), `brama-core/src/tests/Functional/` (new functional tests), `brama-core/docs/` (new docs)
- No breaking changes
- No database migrations
- No new dependencies
