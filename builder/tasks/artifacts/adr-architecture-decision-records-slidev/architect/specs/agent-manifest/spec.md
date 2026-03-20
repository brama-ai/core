## ADDED Requirements

### Requirement: Agent Manifest Format
Every agent service SHALL provide a valid manifest document conforming to the platform manifest schema, containing at minimum: `name`, `version`, `description`, `permissions`, `commands`, `events`, and `a2a_endpoint`.

#### Scenario: Valid manifest accepted
- **WHEN** an agent POSTs its manifest to `/api/v1/internal/agents/register`
- **THEN** the platform validates the manifest against the JSON schema and stores it if valid

#### Scenario: Invalid manifest rejected
- **WHEN** an agent sends a manifest missing the required `a2a_endpoint` field
- **THEN** the platform returns `422 Unprocessable Entity` with a structured validation error listing the missing fields

#### Scenario: Version mismatch on re-registration
- **WHEN** an already-registered agent re-registers with a higher version
- **THEN** the platform updates `version` and `manifest` while preserving `enabled` state and `config`

---

### Requirement: Deterministic Manifest Hash
The platform SHALL compute a deterministic `manifest_hash` for every accepted manifest using normalized JSON serialization and `sha256`.

#### Scenario: Same manifest yields same hash
- **WHEN** an agent re-registers with semantically identical manifest content
- **THEN** the computed `manifest_hash` is unchanged and `storage_sync_required` is not re-triggered solely due to formatting/order differences

#### Scenario: Manifest change yields new hash
- **WHEN** any manifest field value changes
- **THEN** the platform stores a new `manifest_hash` and marks the registry row for re-processing (`storage_sync_required = true`)

#### Scenario: Canonicalization algorithm is versioned
- **WHEN** platform computes `manifest_hash`
- **THEN** it uses a documented canonicalization algorithm version so hash behavior is stable and migration-safe across releases

---

### Requirement: Agent Config Schema
An agent SHALL be able to include an optional `config_schema` field in its manifest as a JSON Schema object describing configurable parameters. When `config_schema` is present, the platform MUST validate it and render editable fields in the admin panel.

#### Scenario: Config schema rendered in admin
- **WHEN** an agent with a non-empty `config_schema` is viewed in the admin panel
- **THEN** the admin panel renders editable form fields matching the config schema properties

#### Scenario: Config defaults applied on first registration
- **WHEN** an agent registers with a `config_schema` containing `default` values
- **THEN** the registry initializes `agent_registry.config` with those defaults for any fields not yet set

---

### Requirement: Agent Migration Contract Declaration
An agent MAY declare migration execution metadata in manifest field `migrations` (`run_command`). If declared, the platform MUST use it as the primary migration entrypoint for core lifecycle flows.

#### Scenario: Declared migration command used on enable
- **WHEN** admin enables an agent that provides `migrations.run_command`
- **THEN** the platform executes that command through the unified migration runner before completing enable

#### Scenario: Missing migration contract falls back to platform defaults
- **WHEN** an agent does not declare `migrations`
- **THEN** the platform uses stack-specific default migration command mapping for that service

#### Scenario: Agent startup cannot override migration owner
- **WHEN** manifest declares `migrations.run_command`
- **THEN** migration execution authority remains in core lifecycle orchestrator, not in autonomous agent startup DDL

---

### Requirement: Agent Capabilities Declaration
An agent SHALL declare a list of named A2A capabilities in its manifest `capabilities` field, listing the intent identifiers it can handle. The platform MUST expose these capabilities via the discovery endpoint.

#### Scenario: Capabilities discoverable
- **WHEN** the platform A2A discovery endpoint is called
- **THEN** each enabled agent's capabilities are included in the response so other agents and OpenClaw can discover what can be delegated

#### Scenario: Unknown capability rejected at A2A layer
- **WHEN** a caller sends an A2A request with an intent not listed in the agent's declared `capabilities`
- **THEN** the platform returns A2A status `failed` with `reason: capability_not_declared`
