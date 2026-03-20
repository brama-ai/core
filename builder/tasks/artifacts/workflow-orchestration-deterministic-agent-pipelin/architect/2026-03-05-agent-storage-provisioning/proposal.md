# Change: Agent Storage Provisioning

## Why
Agents currently share a single Postgres database, a single Redis instance, and a single OpenSearch cluster with no isolation. Agent configuration (description, system_prompt) is stored in core's `agent_registry.config` column rather than in the agent's own storage. Clicking "Enable" only flips a boolean — no storage provisioning occurs. This makes multi-instance deployments impossible and violates the principle that each agent should own its data.

## What Changes
- Agents declare storage requirements in the manifest via a new `storage` section (postgres, redis, opensearch)
- Core validates the `storage` section in `ManifestValidator`
- Three install strategies (Postgres, Redis, OpenSearch) provision resources idempotently when admin clicks "Enable"
- `AgentInstallerService` orchestrates strategies; `AgentMigrationTrigger` calls the agent's migrate endpoint after provisioning
- `installed_at` column added to `agent_registry` to track provisioning state
- Knowledge-agent migrated to its own dedicated database with env vars in compose.yaml
- Agent config storage in core soft-deprecated — agents manage their own config in their own DB
- Codeception unit + functional tests for all strategies and the enable flow

## Impact
- Affected specs: `agent-manifest` (storage section), `agent-registry` (installed_at column), `admin-agent-management` (enable flow)
- Affected code: `apps/core/src/AgentInstaller/` (new), `apps/core/src/AgentRegistry/`, `apps/core/src/Controller/Api/Internal/AgentEnableController.php`, `apps/core/config/`, `apps/knowledge-agent/`, `compose.yaml`
