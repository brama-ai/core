# Change: Add Admin Agent Registry

## Why

The platform architecture defines an Agent Registry that stores available agents, their manifests, config, and enabled/disabled state. Without a formal manifest contract and registry implementation, the admin panel cannot manage agents, and the Event Bus and Command Router have no source of truth for routing decisions. This proposal formalizes the agent manifest format, implements the platform-owned registry, and adds admin UI to control agent lifecycle.

## What Changes

- **Formal agent manifest schema** — JSON structure every agent must provide: `name`, `version`, `description`, `permissions`, `commands`, `events`, `config_schema`, `a2a_endpoint`, and `capabilities`
- **Agent Registry data model** — Postgres table `agent_registry` storing installed agents, their manifest, enabled state, resolved config, and audit timestamps
- **Manifest hash tracking** — core computes deterministic `manifest_hash` on every registration and stores `last_processed_manifest_hash` to detect storage-affecting manifest changes
- **Explicit lifecycle state machine** — registry stores a transparent state (`registered`, `provisioning`, `migration_pending`, `migrating`, `enabled`, `failed_provisioning`, `failed_migration`)
- **Registry API** — platform-internal endpoints to register, enable, disable, and read agent state; used by agent services at boot and by admin panel
- **Provisioning + migration orchestration** — when manifest hash is new/changed, core marks the agent as `storage_sync_required`; on enable core provisions storage and always runs migrations before setting `enabled=true`
- **Core as single migration owner** — DDL migration is executed only by core lifecycle flow; agent startup hooks may report schema status but must not run DDL
- **Unified migration runner contract** — one platform-level migration command wrapper (root script + Make target) used consistently by core lifecycle flows (enable/reconcile)
- **Lifecycle reliability controls** — per-agent distributed lock, retry/backoff policy, and reconcile loop to recover from interrupted runs
- **Lifecycle execution log** — separate run log table with step-level status (`run_id`, `step`, `status`, `error`, duration, actor, `manifest_hash`)
- **Event Bus integration** — Event Bus checks agent registry before dispatching events; disabled agents receive no events
- **Command Router integration** — Command Router resolves agent commands only for enabled agents; unknown commands return graceful fallback
- **Admin UI: Agent Management page** — list all registered agents with status badges, enable/disable toggle, dry-run preview, step timeline, and actionable failure details
- **knowledge-base agent manifest** — concrete manifest for `knowledge-base` agent wired to this registry

## Impact

- Affected specs: agent-manifest (new), agent-registry (new), admin-agent-management (new)
- Affected code: `apps/brama-core/` (registry service, lifecycle orchestrator, event bus, command router), `apps/knowledge-agent/` (manifest file), admin UI routes, Postgres migration, root migration scripts/Make targets
- Depends on: `add-admin-web-login` (admin auth must exist), `bootstrap-platform-foundation` (Symfony + Doctrine in place)
- Extends: `add-knowledge-base-agent` (knowledge-base agent registers itself via this registry)
- No breaking changes to existing platform contracts
