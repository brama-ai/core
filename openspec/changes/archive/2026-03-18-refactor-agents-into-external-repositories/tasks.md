## 1. Scope and Contracts

- [x] 1.1 Inventory all current bundled agents and classify them as:
  - reference agent that may stay in-repo
  - production agent that should move to an external repository
  - agent with unresolved coupling that must be decoupled first
- [x] 1.2 Define the canonical external checkout convention:
  - default path `projects/<agent-name>/`
  - repository naming guidance
  - ownership of Dockerfile, manifest endpoint, migrations, and release tags
- [x] 1.3 Define the compose integration contract for external agents:
  - service naming
  - labels required for discovery
  - network attachment
  - env file expectations
  - healthcheck requirements

## 2. Platform Runtime Changes

- [x] 2.1 Add a compose loading mechanism that supports external agent fragments without editing the
  platform base files for each new agent
- [x] 2.2 Add Makefile targets or scripts for:
  - cloning an agent repository into `projects/`
  - listing detected external agent compose fragments
  - starting, stopping, and upgrading a named external agent
- [x] 2.3 Ensure agent discovery, convention verification, and admin registry flows work the same
  way for in-repo agents and external checkouts
- [x] 2.4 Define CI expectations for external agents:
  - repository-local tests remain in the agent repo
  - platform compatibility checks remain in the core repo

## 3. Migration Strategy

- [x] 3.1 Choose one current agent as the pilot externalization candidate and document why
- [x] 3.2 Create a migration playbook for moving an agent from `apps/<name>` to its own repository
- [x] 3.3 Define compatibility rules during transition:
  - no mixed service names
  - stable manifest schema
  - stable admin URLs
  - stable A2A endpoint paths

## 4. Documentation

- [x] 4.1 Create developer-facing documentation for external agent repository structure and required
  platform contracts
- [x] 4.2 Create operator documentation for the onboarding flow:
  - `git clone` into `projects/<agent-name>/`
  - enable compose fragment
  - run setup / migrations
  - verify health and discovery
- [x] 4.3 Update agent requirement docs with the compose, manifest, health, and migration contract
- [x] 4.4 Add an example external-agent template or reference repository layout
- [x] 4.5 Add English mirrors or folder-based bilingual docs wherever the document is
  operator-facing

## 5. Quality Checks

- [x] 5.1 Validate both OpenSpec changes and capability boundaries against active discovery and
  marketplace proposals
- [x] 5.2 Run the relevant compatibility and convention checks for the pilot agent workflow
