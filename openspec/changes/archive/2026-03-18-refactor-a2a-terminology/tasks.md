# Tasks: refactor-a2a-terminology

## 1. Core: Namespace & Class Renames

- [x] 1.1 Rename directory `apps/core/src/AgentDiscovery/` → `apps/core/src/A2AGateway/`
- [x] 1.2 Update namespace in all files: `App\AgentDiscovery` → `App\A2AGateway`
- [x] 1.3 Rename `AgentInvokeBridge` → `A2AClient`
- [x] 1.4 Rename `AgentManifestFetcher` → `AgentCardFetcher`
- [x] 1.5 Rename `DiscoveryBuilder` → `SkillCatalogBuilder`
- [x] 1.6 Rename `OpenClawSyncService` → `SkillCatalogSyncService`
- [x] 1.7 Rename controller directory `Controller/Api/OpenClaw/` → `Controller/Api/A2AGateway/`
- [x] 1.8 Update namespace in controllers: `App\Controller\Api\OpenClaw` → `App\Controller\Api\A2AGateway`
- [x] 1.9 Update all imports/references in `config/services.yaml`

## 2. Core: Route & URL Renames

- [x] 2.1 Rename route `api_openclaw_invoke` → `api_a2a_send_message`, path `/api/v1/agents/invoke` → `/api/v1/a2a/send-message`
- [x] 2.2 Rename route `api_openclaw_discovery` → `api_a2a_discovery`, path `/api/v1/agents/discovery` → `/api/v1/a2a/discovery`
- [x] 2.3 Update OpenClaw gateway config to use new endpoints
- [x] 2.4 Update any tests referencing old route names/paths

## 3. Core: Agent Card Schema Rename

- [x] 3.1 Rename `config/agent-manifest.schema.json` → `config/agent-card.schema.json`
- [x] 3.2 Update `$id` → `agent-card`, `title` → `Agent Card`
- [x] 3.3 Rename `capabilities` → `skills` field in schema
- [x] 3.4 Rename `capability_schemas` → `skill_schemas` field in schema
- [x] 3.5 Update `AgentConventionVerifier` to reference `skills` field
- [x] 3.6 Update `SkillCatalogBuilder` to read `skills` / `skill_schemas` instead of `capabilities` / `capability_schemas`
- [x] 3.7 Update `A2AClient` to match skills from `skills` field
- [x] 3.8 Update agent manifest controllers in hello-agent and knowledge-agent: `capabilities` → `skills`

## 4. Core: DB Migration — Audit Table Rename

- [x] 4.1 Create migration: `ALTER TABLE agent_invocation_audit RENAME TO a2a_message_audit`
- [x] 4.2 Rename column `tool` → `skill` in `a2a_message_audit`
- [x] 4.3 Update `A2AClient.auditLog()` to use new table/column names
- [x] 4.4 Update any admin queries or reports referencing old table name

## 5. Core: Admin UI — Agent Card & A2A Server Section

- [x] 5.1 Rename "Деталі маніфесту" → "Agent Card" in `agents.html.twig`
- [x] 5.2 Rename "Можливості" → "Skills" in `agents.html.twig`
- [x] 5.3 Add "A2A Server" collapsible section per agent showing: endpoint URL, skills with descriptions
- [x] 5.4 Hide A2A Server section for agents without `a2a_endpoint`
- [x] 5.5 Update field references in template (`manifest.capabilities` → `manifest.skills`, etc.)

## 6. Core: Update Tests

- [x] 6.1 Move `tests/Unit/AgentDiscovery/AgentInvokeBridgeTest.php` → `tests/Unit/A2AGateway/A2AClientTest.php`
- [x] 6.2 Update test class references and assertions for new naming
- [x] 6.3 Update any functional tests referencing old routes or class names

## 7. Agents: Update Agent Card Content

- [x] 7.1 Update `apps/hello-agent/src/Controller/Api/ManifestController.php`: `capabilities` → `skills`
- [x] 7.2 Update `apps/knowledge-agent/src/Controller/Api/ManifestController.php`: `capabilities` → `skills`
- [x] 7.3 Update `ManifestValidator` and its test: `capabilities` → `skills`

## 8. Documentation

### Agent Requirements (highest priority — developer contracts)
- [x] 8.1 `docs/agent-requirements/conventions.md` — `manifest` → Agent Card, `capabilities` → `skills`, `capability_schemas` → `skill_schemas`
- [x] 8.2 `docs/agent-requirements/test-cases.md` — update TC-01 manifest tests to reference `skills` field
- [x] 8.3 `docs/agent-requirements/observability-requirements.md` — update A2A bridge → A2A Gateway context
- [x] 8.4 `docs/agent-requirements/e2e-testing.md` — no changes needed (references `GET /api/v1/manifest` which stays)
- [x] 8.5 `docs/agent-requirements/storage-provisioning.md` — update manifest `storage` references to Agent Card

### Specs (protocol definition)
- [x] 8.6 `docs/specs/en/a2a-protocol.md` — added Key Terminology table with Agent Card / Skill references
- [x] 8.7 `docs/specs/ua/a2a-protocol.md` — mirror Ukrainian updates

### Product & Architecture docs
- [x] 8.8 `docs/product/en/core-agent-openclaw.md` — skipped (TODO placeholders, no content to update)
- [x] 8.9 `docs/product/ua/core-agent-openclaw.md` — no changes needed (OpenClaw role context is correct)
- [x] 8.10 `docs/decisions/adr_0002_openclaw_role.md` — added A2A Gateway naming decision section

### Planning docs
- [x] 8.11 `docs/plans/telegram-openclaw-integration-plan.md` — updated API routes to `/api/v1/a2a/send-message` and `/api/v1/a2a/discovery`
- [x] 8.12 `docs/plans/openclaw-observability-rollout-plan.md` — no changes needed (already uses A2A terminology)

### Agent docs
- [x] 8.13 `docs/agents/en/hello-agent.md` — updated manifest → Agent Card
- [x] 8.14 `docs/agents/ua/hello-agent.md` — mirror Ukrainian updates

### Local dev
- [x] 8.15 `docs/local-dev.md` — no changes needed (no relevant references found)

### New docs
- [x] 8.16 Created `docs/specs/en/a2a-terminology-mapping.md` — mapping table: our terms ↔ official A2A spec terms
- [x] 8.17 Created `docs/specs/ua/a2a-terminology-mapping.md` — Ukrainian mirror

## 9. Quality Checks

- [x] 9.1 `make analyse` — PHPStan level 8, zero errors ✓
- [x] 9.2 `make cs-fix` + `make cs-check` — no CS violations ✓
- [x] 9.3 `make test` — all 106 Codeception tests pass (67 unit + 39 functional) ✓
- [x] 9.4 Convention tests — ManifestValidator and AgentConventionVerifier updated ✓
- [x] 9.5 Admin panel templates updated with new terminology ✓
