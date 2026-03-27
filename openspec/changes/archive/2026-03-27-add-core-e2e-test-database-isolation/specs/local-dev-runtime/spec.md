## ADDED Requirements

### Requirement: Dedicated Core E2E Runtime Surface
The local development topology SHALL provide a dedicated Core E2E runtime surface that is configured independently from the default Core runtime.

#### Scenario: Core E2E runtime uses isolated database configuration
- **WHEN** a developer starts the E2E runtime topology
- **THEN** the Core E2E runtime uses `DATABASE_URL` targeting `ai_community_platform_e2e`
- **AND** the default Core runtime remains configured for `ai_community_platform`

#### Scenario: E2E runtime is optional for normal development
- **WHEN** a developer starts the default local runtime without E2E overlay
- **THEN** only the default Core runtime is required
- **AND** E2E-specific runtime components are not mandatory for day-to-day development
