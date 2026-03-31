## ADDED Requirements

### Requirement: Local k3s Cluster Prerequisites

The platform SHALL document and verify the prerequisites for running the platform on a local k3s
cluster via Rancher Desktop.

#### Scenario: Rancher Desktop prerequisites are documented

- **GIVEN** a developer workstation with Rancher Desktop installed
- **WHEN** the developer reads the k3s setup documentation
- **THEN** the documentation SHALL specify the minimum Rancher Desktop version
- **AND** the documentation SHALL specify that the k3s (not dockerd) container runtime must be selected
- **AND** the documentation SHALL specify the minimum recommended resource allocation (CPU, memory)

#### Scenario: Kube context is defined and verifiable

- **GIVEN** Rancher Desktop k3s is running
- **WHEN** the operator runs `kubectl config current-context`
- **THEN** the output SHALL match the documented expected context name (e.g., `rancher-desktop`)
- **AND** `kubectl get nodes` SHALL show at least one node in `Ready` state

#### Scenario: Target namespace is created

- **GIVEN** the kube context points to the local k3s cluster
- **WHEN** the operator applies the namespace manifest
- **THEN** `kubectl get namespace brama` SHALL succeed
- **AND** the namespace SHALL carry the label `app.kubernetes.io/part-of: brama`

### Requirement: Shared ConfigMap and Secret Strategy

The platform SHALL provide a shared ConfigMap and Secret strategy that maps `.env.deployment`
values to Kubernetes-native configuration resources.

#### Scenario: ConfigMap contains non-secret runtime values

- **GIVEN** the shared ConfigMap manifest is applied to the `brama` namespace
- **WHEN** `kubectl get configmap brama-config -n brama -o yaml` is run
- **THEN** the ConfigMap SHALL contain keys for service hostnames, ports, and constructed URLs
  (e.g., `POSTGRES_HOST`, `REDIS_URL`, `OPENSEARCH_URL`, `DATABASE_URL`)
- **AND** no credential or secret values SHALL appear in the ConfigMap

#### Scenario: Secret contains credentials and sensitive values

- **GIVEN** the shared Secret manifest is applied to the `brama` namespace
- **WHEN** `kubectl get secret brama-secrets -n brama` is run
- **THEN** the Secret SHALL exist and contain keys for credentials
  (e.g., `POSTGRES_PASSWORD`, `RABBITMQ_PASSWORD`, `EDGE_AUTH_JWT_SECRET`)
- **AND** the Secret type SHALL be `Opaque`

#### Scenario: Mapping from .env.deployment is documented

- **GIVEN** the k3s setup documentation exists
- **WHEN** the operator reads the configuration mapping section
- **THEN** every variable in `.env.deployment.example` SHALL be mapped to either the ConfigMap
  or the Secret with a clear rationale for the placement
- **AND** the documentation SHALL explain how services reference these resources via `envFrom`

### Requirement: Infrastructure Services as k3s Deployments

The platform SHALL deploy PostgreSQL, Redis, RabbitMQ, and OpenSearch as k3s Deployments with
Services and PersistentVolumeClaims in the `brama` namespace.

#### Scenario: PostgreSQL deployment reaches healthy state

- **WHEN** the PostgreSQL deployment manifest is applied
- **THEN** the PostgreSQL pod SHALL reach `Running` state
- **AND** `kubectl exec` into the pod with `pg_isready` SHALL return success
- **AND** a Service named `postgres` SHALL be created exposing port 5432

#### Scenario: Redis deployment reaches healthy state

- **WHEN** the Redis deployment manifest is applied
- **THEN** the Redis pod SHALL reach `Running` state
- **AND** `kubectl exec` into the pod with `redis-cli ping` SHALL return `PONG`
- **AND** a Service named `redis` SHALL be created exposing port 6379

#### Scenario: RabbitMQ deployment reaches healthy state

- **WHEN** the RabbitMQ deployment manifest is applied
- **THEN** the RabbitMQ pod SHALL reach `Running` state
- **AND** `kubectl exec` into the pod with `rabbitmq-diagnostics -q ping` SHALL return success
- **AND** a Service named `rabbitmq` SHALL be created exposing ports 5672 and 15672

#### Scenario: OpenSearch deployment reaches healthy state

- **WHEN** the OpenSearch deployment manifest is applied
- **THEN** the OpenSearch pod SHALL reach `Running` state
- **AND** a curl to `http://opensearch:9200` from within the cluster SHALL return a JSON response
  with a `cluster_name` field
- **AND** a Service named `opensearch` SHALL be created exposing port 9200

#### Scenario: Infrastructure services use persistent storage

- **WHEN** all infrastructure deployment manifests are applied
- **THEN** each stateful service (PostgreSQL, Redis, RabbitMQ, OpenSearch) SHALL have a
  PersistentVolumeClaim using the k3s default `local-path` StorageClass
- **AND** data SHALL survive pod restarts within the same k3s cluster

#### Scenario: No CrashLoopBackOff in infrastructure layer

- **WHEN** all infrastructure manifests are applied and pods have had 60 seconds to stabilize
- **THEN** `kubectl get pods -n brama -l app.kubernetes.io/component=infra` SHALL show all pods
  in `Running` state with no `CrashLoopBackOff` or `Error` status

### Requirement: Core Runtime with Readiness and Liveness Probes

The core service SHALL be deployable as a k3s Deployment with HTTP-based readiness and liveness
probes targeting the existing `/health` endpoint.

#### Scenario: Core deployment reaches ready state

- **GIVEN** all infrastructure services are healthy in the `brama` namespace
- **WHEN** the core deployment manifest is applied
- **THEN** the core pod SHALL reach `Ready` state within 120 seconds
- **AND** the readiness probe SHALL target `GET /health` on port 80

#### Scenario: Core liveness probe detects failures

- **GIVEN** the core pod is running
- **WHEN** the liveness probe checks `GET /health` on port 80
- **THEN** a successful response (HTTP 200) SHALL keep the pod alive
- **AND** consecutive failures SHALL trigger a pod restart by the kubelet

#### Scenario: Core health endpoint returns expected response in k3s

- **GIVEN** the core pod is ready
- **WHEN** `kubectl exec` is used to curl `http://core:80/health` from another pod in the namespace
- **THEN** the response SHALL be HTTP 200 with body `{"status":"ok","service":"core-platform"}`

#### Scenario: Core service is reachable via cluster DNS

- **GIVEN** the core Service is created in the `brama` namespace
- **WHEN** another pod in the same namespace resolves `core` or `core.brama.svc`
- **THEN** the DNS resolution SHALL succeed and route to the core pod on port 80

### Requirement: Reference Agent Runtime in k3s

The platform SHALL prove agent runtime viability by deploying at least one reference agent
(hello-agent) in the local k3s cluster and verifying connectivity with the core service.

#### Scenario: Hello-agent deployment reaches ready state

- **GIVEN** the core service and required infrastructure services are healthy
- **WHEN** the hello-agent deployment manifest is applied
- **THEN** the hello-agent pod SHALL reach `Ready` state
- **AND** the hello-agent health endpoint SHALL return a successful HTTP response

#### Scenario: Core can reach the reference agent over cluster networking

- **GIVEN** both core and hello-agent pods are ready in the `brama` namespace
- **WHEN** a request is made from the core pod to `http://hello-agent:80/` (or the agent's
  documented health path)
- **THEN** the request SHALL succeed over cluster DNS without external routing

#### Scenario: Reference agent receives shared configuration

- **GIVEN** the hello-agent deployment references the shared ConfigMap and Secret
- **WHEN** the hello-agent pod starts
- **THEN** environment variables from `brama-config` and `brama-secrets` SHALL be available
  inside the container
- **AND** the agent SHALL be able to connect to infrastructure services using those values

### Requirement: Operator Access via Port-Forward and Optional Ingress

The platform SHALL provide documented access paths for operators to reach platform services
from a local browser when running on k3s.

#### Scenario: Core is accessible via kubectl port-forward

- **GIVEN** the core service is running in the `brama` namespace
- **WHEN** the operator runs `kubectl port-forward svc/core 8081:80 -n brama`
- **THEN** `http://localhost:8081/health` SHALL return the core health response
- **AND** the operator can interact with the core admin interface at `http://localhost:8081/`

#### Scenario: Infrastructure management UIs are accessible via port-forward

- **GIVEN** infrastructure services are running in the `brama` namespace
- **WHEN** the operator runs the documented port-forward commands for RabbitMQ management
  (`kubectl port-forward svc/rabbitmq 15672:15672 -n brama`)
- **THEN** the RabbitMQ management UI SHALL be accessible at `http://localhost:15672/`

#### Scenario: Access paths are documented with known gaps

- **GIVEN** the k3s setup documentation exists
- **WHEN** the operator reads the access section
- **THEN** the documentation SHALL list all port-forward commands for each service
- **AND** the documentation SHALL note any known gaps or temporary workarounds
  (e.g., services not yet exposed, Traefik ingress not yet configured)
- **AND** the documentation SHALL describe the optional Traefik Ingress path for operators
  who prefer hostname-based routing
