## MODIFIED Requirements

### Requirement: A2A Gateway Architecture
The platform core SHALL act as an A2A Gateway — accepting requests as an A2A Server (from OpenClaw) and forwarding them as an A2A Client (to remote agents). All gateway services SHALL reside in the `App\A2AGateway` namespace. Agent discovery SHALL use a provider strategy pattern (`AgentDiscoveryProviderInterface`) that supports both Docker Compose (Traefik API) and Kubernetes (label-based Service query) runtimes. The active provider SHALL be selected by the `AGENT_DISCOVERY_PROVIDER` environment variable (`traefik`, `kubernetes`, or `auto`).

#### Scenario: Core forwards message through gateway
- **WHEN** OpenClaw sends a skill invocation via `POST /api/v1/a2a/send-message`
- **THEN** the `A2AClient` service (in `App\A2AGateway`) resolves the skill to an enabled agent, sends the request to the agent's A2A endpoint, and returns the response

#### Scenario: Discovery uses Traefik provider in Docker Compose
- **WHEN** `AGENT_DISCOVERY_PROVIDER` is set to `traefik` or auto-detection finds no Kubernetes service account token
- **THEN** the `TraefikDiscoveryProvider` queries `http://traefik:8080/api/http/services` and returns agents matching the `*-agent@docker` pattern

#### Scenario: Discovery uses Kubernetes provider in k3s
- **WHEN** `AGENT_DISCOVERY_PROVIDER` is set to `kubernetes` or auto-detection finds a Kubernetes service account token at `/var/run/secrets/kubernetes.io/serviceaccount/token`
- **THEN** the `KubernetesDiscoveryProvider` queries the Kubernetes API for Services with label `ai.platform.agent=true` in the current namespace and returns agents with hostname `<service-name>.<namespace>.svc.cluster.local`

#### Scenario: Discovery provider auto-detection
- **WHEN** `AGENT_DISCOVERY_PROVIDER` is set to `auto` or is empty
- **THEN** the `AgentDiscoveryProviderFactory` checks for the presence of a Kubernetes service account token file and selects `KubernetesDiscoveryProvider` if found, otherwise `TraefikDiscoveryProvider`

#### Scenario: Kubernetes discovery graceful degradation
- **WHEN** the Kubernetes API is unreachable or returns a non-success status code or the service account credentials are missing
- **THEN** the `KubernetesDiscoveryProvider` logs a warning and returns an empty list without throwing an exception

## ADDED Requirements

### Requirement: Kubernetes Agent Service Labels
Agent Services deployed via the Helm chart SHALL include the label `ai.platform.agent: "true"` and `ai.platform.agent-name: <agent-key>-agent` on the Service metadata. Agent Deployment pod templates SHALL include the label `ai.platform.agent: "true"`. These labels enable the `KubernetesDiscoveryProvider` to discover agents via the Kubernetes API.

#### Scenario: Helm chart renders agent labels on Service
- **WHEN** an agent is enabled in `values.yaml`
- **THEN** `helm template` renders a Service with `ai.platform.agent: "true"` and `ai.platform.agent-name` labels in metadata

#### Scenario: Helm chart renders agent labels on pod template
- **WHEN** an agent is enabled in `values.yaml`
- **THEN** `helm template` renders a Deployment whose pod template includes `ai.platform.agent: "true"` label

### Requirement: Core RBAC for Service Discovery
The Helm chart SHALL create a Role granting `list` permission on `services` resources and a RoleBinding binding that Role to the core ServiceAccount. This RBAC configuration enables the `KubernetesDiscoveryProvider` to query agent Services in the release namespace.

#### Scenario: RBAC allows core to list services
- **WHEN** the Helm chart is installed
- **THEN** the core ServiceAccount has permission to `list services` in the release namespace via a Role and RoleBinding defined in `templates/core/rbac.yaml`
