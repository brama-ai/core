## MODIFIED Requirements

### Requirement: Pipeline Phase Integration
The translater agent SHALL run as Phase 6b (optional, after documenter, before summarizer) in the Ultraworks pipeline.

The security-review agent SHALL run as Phase 5b (optional, after auditor loop, before documenter) in the Ultraworks pipeline.

The sequential Foundry runtime SHALL expose its agent workflow through `agentic-development/foundry.sh` as the canonical operator entrypoint, while preserving compatibility wrappers for legacy sequential pipeline scripts during migration.

#### Scenario: Translater triggered by i18n changes
- **WHEN** a pipeline change touches `translations/*.yaml`, `*.html.twig` with trans filter, or `docs/**/*.md`
- **THEN** Sisyphus delegates to `s-translater` after documenter completes

#### Scenario: Security-review triggered by security-sensitive changes
- **WHEN** a pipeline change touches auth controllers, security voters, form types, file upload handlers, or HTTP client code
- **THEN** Sisyphus delegates to `s-security-review` after auditor loop completes

#### Scenario: Both agents skipped when not relevant
- **WHEN** a pipeline change only modifies test files or documentation structure
- **THEN** both translater and security-review phases are skipped

#### Scenario: Sequential Foundry runtime is launched by operators
- **WHEN** an operator starts the sequential Foundry workflow
- **THEN** they use `agentic-development/foundry.sh` as the primary runtime command
- **AND** the runtime executes the same sequential agent phases previously handled by the legacy sequential pipeline entrypoints
