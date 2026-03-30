# Tasks: complete-admin-agent-registry

Completes the remaining work from the original `add-admin-agent-registry` change (archived as `2026-03-21-add-admin-agent-registry`). The original change was 30/38 tasks complete. This proposal covers the final 8 tasks to bring the feature to 100%.

## 1. E2E Tests — Agent Install Flow

- [x] 1.1 Add E2E test `agent_install_test.js`: register a fake marketplace agent via API, navigate to Marketplace tab, click Install button, verify agent moves to Installed tab with `not_installed` → `disabled` status transition
- [x] 1.2 Extend `AgentsPage.js` page object with `installAgent(name)` action if not already exercised end-to-end (method exists but no test calls it through the full UI flow)

## 2. E2E Tests — Config Save and Persistence

- [x] 2.1 Add E2E scenario in `agent_settings_test.js`: fill description textarea, fill system_prompt textarea, click Save, reload page, verify saved values persist in the form fields
- [x] 2.2 Add E2E scenario: save config with empty fields, verify no server error and fields remain empty after reload

## 3. E2E Tests — Violations Modal and Admin Iframe

- [x] 3.1 Add E2E test for violations modal: register a fake agent with known convention violations (via API with invalid manifest fields), navigate to agents page, verify degraded badge is visible, click badge to open violations modal, verify violation text is displayed
- [x] 3.2 Add E2E scenario in `agent_settings_test.js`: verify that for an agent with `admin_url` in its manifest, the admin iframe element is present on the settings page (use `agentSettingsPage.seeAdminIframe()`)

## 4. Functional Test — Agent Delete API

- [x] 4.1 Add Codeception functional test `AgentDeleteCest.php`: test `DELETE /api/v1/internal/agents/{name}` endpoint — verify installed agent is uninstalled (installed_at cleared), verify audit log entry with action `uninstalled`, verify agent remains in registry (marketplace state), verify 404 for non-existent agent

## 5. Settings Page i18n

- [x] 5.1 Replace hardcoded Ukrainian strings in `agent_settings.html.twig` with `|trans` filter calls (e.g., `'agent_settings.back'|trans`, `'agent_settings.save'|trans`, `'agent_settings.storage_info'|trans`, etc.) to match the pattern used in `agents.html.twig`
- [x] 5.2 Add corresponding translation keys to the messages translation file (Ukrainian values matching current hardcoded strings)

## 6. Agent Registry Spec Update

- [x] 6.1 Update `agent-registry` spec Purpose from "TBD" to a proper description of the capability
- [x] 6.2 Add spec requirements for admin agent management UI: list view with two tabs (Installed/Marketplace), agent detail/settings view, enable/disable/install/delete lifecycle actions via admin panel

## 7. Documentation

- [x] 7.1 Create `docs/guides/agent-registry/en/agent-registry.md` — developer-facing guide covering: registry data model, agent lifecycle (register → install → enable → disable → delete), admin UI features, API endpoints for agent management, health polling behavior
- [x] 7.2 Create `docs/guides/agent-registry/ua/agent-registry.md` — Ukrainian canonical version of the same guide

## 8. Quality Gate

- [x] 8.1 Run `phpstan analyse` — zero errors at level 8
- [x] 8.2 Run `php-cs-fixer check` — no violations
- [x] 8.3 Run `codecept run` — all suites pass
- [x] 8.4 Run `make e2e` — all Playwright E2E tests pass (including new agent registry tests)
