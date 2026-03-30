# Local k3s Runtime Validation Specification

## ADDED Requirements

### Requirement: Cluster Readiness Validation
The platform SHALL validate that the local k3s cluster is operational before any deployment or runtime checks proceed.

#### Scenario: Confirming cluster node is reachable
- **WHEN** the operator runs `kubectl get nodes`
- **THEN** at least one node MUST be in `Ready` state
- **AND** the context MUST be `rancher-desktop`

#### Scenario: Confirming target namespace exists
- **WHEN** the operator runs `make k8s-ns` or `kubectl get ns brama`
- **THEN** the namespace `brama` MUST exist and be `Active`

#### Scenario: Confirming no critical system pods are failing
- **WHEN** the operator checks pods in `kube-system`
- **THEN** no pods SHALL be in `CrashLoopBackOff` or `Error` state

### Requirement: Infrastructure Layer Validation
The platform SHALL validate that all in-cluster infrastructure services deployed by Bitnami sub-charts are healthy and accepting connections.

#### Scenario: Validating PostgreSQL readiness
- **WHEN** the operator executes `psql -U app -d ai_community_platform -c "SELECT 1;"` inside the PostgreSQL pod
- **THEN** the query MUST return successfully
- **AND** the pod MUST be in `Running` state

#### Scenario: Validating Redis readiness
- **WHEN** the operator executes `redis-cli ping` inside the Redis pod
- **THEN** the response MUST be `PONG`
- **AND** the pod MUST be in `Running` state

#### Scenario: Validating RabbitMQ readiness
- **WHEN** the operator executes `rabbitmqctl status` inside the RabbitMQ pod
- **THEN** the output MUST include the RabbitMQ version line without errors
- **AND** the pod MUST be in `Running` state

#### Scenario: Documenting OpenSearch skip
- **WHEN** OpenSearch is disabled in `values-k3s-dev.yaml` (`opensearch.enabled: false`)
- **THEN** the validation runbook MUST document that OpenSearch is skipped for local k3s
- **AND** MUST note that Docker Compose OpenSearch is used instead

### Requirement: Core Runtime Validation
The platform SHALL validate that the core application pod is running, healthy, and accessible to operators.

#### Scenario: Validating core pod readiness
- **WHEN** the operator checks `kubectl get pods -n brama -l app.kubernetes.io/component=core`
- **THEN** the pod MUST show status `Running` with `READY 1/1`

#### Scenario: Validating core health endpoint via exec
- **WHEN** the operator runs `kubectl exec` to curl `http://localhost/health` inside the core pod
- **THEN** the response MUST be `{"status":"ok","timestamp":"..."}`

#### Scenario: Validating operator access via port-forward
- **WHEN** the operator runs `kubectl port-forward -n brama svc/brama-core 8080:80`
- **THEN** `curl http://localhost:8080/health` MUST return the health response

#### Scenario: Validating operator access via Traefik ingress
- **WHEN** the operator has added `127.0.0.1 core.localhost` to `/etc/hosts`
- **AND** Traefik ingress is enabled with `className: traefik`
- **THEN** `http://core.localhost/health` MUST return the health response

### Requirement: Reference Agent Runtime Validation
The platform SHALL validate that at least one reference agent (hello-agent) is running, healthy, and discoverable by the core platform.

#### Scenario: Validating hello-agent pod readiness
- **WHEN** the operator checks `kubectl get pods -n brama -l app.kubernetes.io/component=agent-hello`
- **THEN** the pod MUST show status `Running` with `READY 1/1`

#### Scenario: Validating hello-agent health endpoint
- **WHEN** the operator runs `kubectl exec` to curl `http://localhost/health` inside the hello-agent pod
- **THEN** the response MUST include `{"status":"ok","service":"hello-agent"}`

#### Scenario: Validating Kubernetes agent discovery labels
- **WHEN** the operator runs `kubectl get svc -n brama -l ai.platform.agent=true`
- **THEN** the service `brama-agent-hello` MUST be listed
- **AND** the service MUST have label `ai.platform.agent-name: hello-agent`

#### Scenario: Validating core-to-agent connectivity via cluster DNS
- **WHEN** the operator runs `kubectl exec` from the core pod to curl `http://brama-agent-hello.brama.svc.cluster.local/health`
- **THEN** the hello-agent health response MUST be returned

### Requirement: Verified Operator Runbook
The platform SHALL provide a bilingual operator runbook that enables repeatable local k3s validation without tribal knowledge.

#### Scenario: Following the runbook from scratch
- **WHEN** a new operator follows the documented validation flow from prerequisites through all five stages
- **THEN** they MUST be able to confirm cluster health, infrastructure health, core health, agent health, and local access using only documented commands

#### Scenario: Using the minimum re-validation sequence
- **WHEN** the operator runs the documented 6-step quick re-validation sequence
- **THEN** all six steps passing MUST confirm the local k3s runtime is verified

#### Scenario: Diagnosing a failure using the known issues table
- **WHEN** a validation step fails
- **THEN** the runbook MUST provide a known issues table with at least: connection refused, pending pods, ImagePullBackOff, CrashLoopBackOff, exec format error, missing hosts entry, and wrong kubectl context
- **AND** each issue MUST have a documented cause and fix
