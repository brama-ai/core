## ADDED Requirements

### Requirement: OpenClaw Tool Sync on Registry Change
The platform SHALL push an updated tool catalog to OpenClaw whenever an agent's enabled state changes in the registry.

#### Scenario: Tool added to OpenClaw on agent enable
- **WHEN** an admin enables an agent via the registry
- **THEN** the platform's `OpenClawSyncService` pushes the updated discovery payload to OpenClaw within 5 seconds

#### Scenario: Tool removed from OpenClaw on agent disable
- **WHEN** an admin disables an agent via the registry
- **THEN** the platform pushes an updated discovery payload excluding the disabled agent's tools

#### Scenario: Push fails gracefully
- **WHEN** OpenClaw is temporarily unreachable during a push
- **THEN** the platform logs the failure, does not block the registry change, and retries on the next poll cycle

---

### Requirement: OpenClaw Polling Discovery on Startup
OpenClaw SHALL be configured to poll the platform discovery endpoint on startup and on a regular interval to load the current tool catalog.

#### Scenario: OpenClaw loads tools on startup
- **WHEN** OpenClaw starts (or restarts) with the discovery endpoint configured
- **THEN** it fetches the tool catalog and registers all returned tools before accepting requests

#### Scenario: OpenClaw periodic refresh
- **WHEN** the polling interval (default: 30 seconds) elapses
- **THEN** OpenClaw re-fetches the discovery endpoint and updates its tool list, adding new tools and removing disabled ones

---

### Requirement: Admin OpenClaw Sync Status
The admin agent management page SHALL show each enabled agent's synchronization status with OpenClaw.

#### Scenario: Sync confirmed badge shown
- **WHEN** an agent's tools appear in OpenClaw's last confirmed tool catalog
- **THEN** the agent row shows a green "Зареєстровано" badge in the OpenClaw column

#### Scenario: Sync pending badge shown
- **WHEN** an agent was just enabled but the last OpenClaw sync confirmation is older than 2 poll intervals
- **THEN** the agent row shows an amber "Очікується" badge

#### Scenario: Sync failed badge shown
- **WHEN** the last push attempt to OpenClaw failed and the poll confirmation has not arrived
- **THEN** the agent row shows a red "Не підключено" badge with a tooltip showing the last error

#### Scenario: Admin triggers manual sync
- **WHEN** admin clicks the "Синхронізувати" button on the agents page
- **THEN** the platform immediately pushes the discovery payload to OpenClaw and updates sync status on the page

---

### Requirement: OpenClaw Security Boundary
OpenClaw MUST interact with agent services exclusively through the platform's A2A invoke bridge and MUST NOT have network access to agent service endpoints directly.

#### Scenario: OpenClaw cannot reach agent service directly
- **WHEN** OpenClaw is configured according to the Docker Compose network topology
- **THEN** agent service containers are on an internal network not exposed to OpenClaw's container

#### Scenario: All agent invocations logged
- **WHEN** OpenClaw invokes any tool via the bridge
- **THEN** the platform audit log records the invocation regardless of the A2A outcome, preserving a complete call trace
