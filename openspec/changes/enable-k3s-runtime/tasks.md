# Implementation Tasks

## 1. Prepare Local k3s Target
- [x] 1.1 Document Rancher Desktop prerequisites for local k3s (version, container runtime, resource allocation)
- [x] 1.2 Define the expected kube context name for local validation (`rancher-desktop`)
- [x] 1.3 Create the target namespace manifest (`brama`) with shared labels (`app.kubernetes.io/part-of: brama`)

**Acceptance checks**
- `kubectl config current-context` points to the expected Rancher Desktop context
- `kubectl get nodes` shows at least one `Ready` node
- `kubectl get namespace brama` succeeds after namespace creation
- Namespace carries the label `app.kubernetes.io/part-of: brama`

## 2. Add Shared Config and Secrets Model
- [x] 2.1 Create ConfigMap manifest (`brama-config`) for non-secret runtime values (hostnames, ports, URLs)
- [x] 2.2 Create Secret manifest (`brama-secrets`) for credentials and sensitive runtime values
- [x] 2.3 Document the mapping from `.env.deployment.example` variables to ConfigMap/Secret keys
- [x] 2.4 Verify services can reference config via `envFrom` with `configMapRef` and `secretRef`

**Acceptance checks**
- `kubectl apply -f` for shared config resources succeeds without schema errors
- `kubectl get configmap brama-config -n brama` shows expected keys
- `kubectl get secret brama-secrets -n brama` shows expected keys
- No credential values appear in the ConfigMap

## 3. Boot Infrastructure Services
- [x] 3.1 Add Deployment, Service, and PVC manifests for PostgreSQL (pgvector/pgvector:pg16)
- [x] 3.2 Add Deployment, Service, and PVC manifests for Redis (redis:7-alpine)
- [x] 3.3 Add Deployment, Service, and PVC manifests for RabbitMQ (rabbitmq:3.13-management-alpine)
- [x] 3.4 Add Deployment, Service, and PVC manifests for OpenSearch (opensearchproject/opensearch:2.11.1)

**Acceptance checks**
- All infrastructure pods reach `Running` state within 120 seconds
- `kubectl get pods -n brama -l app.kubernetes.io/component=infra` shows no `CrashLoopBackOff`
- `pg_isready` succeeds inside the PostgreSQL pod
- `redis-cli ping` returns `PONG` inside the Redis pod
- `rabbitmq-diagnostics -q ping` succeeds inside the RabbitMQ pod
- Curl to `http://opensearch:9200` from within the cluster returns a JSON response

## 4. Boot Core Runtime
- [x] 4.1 Add Deployment manifest for the core service with `envFrom` referencing shared config/secrets
- [x] 4.2 Add Service manifest exposing the core HTTP surface on port 80
- [x] 4.3 Configure readiness probe: HTTP GET `/health` port 80, initialDelay 10s, period 5s
- [x] 4.4 Configure liveness probe: HTTP GET `/health` port 80, initialDelay 30s, period 10s

**Acceptance checks**
- Core pod reaches `Ready` state within 120 seconds
- `kubectl exec` curl to `http://core:80/health` returns `{"status":"ok","service":"core-platform"}`
- Core service is resolvable via cluster DNS (`core.brama.svc`)

## 5. Boot One Reference Agent
- [x] 5.1 Add Deployment and Service manifests for hello-agent (`ghcr.io/nmdimas/a2a-hello-agent:main`)
- [x] 5.2 Configure hello-agent `envFrom` to reference shared ConfigMap and Secret
- [x] 5.3 Verify hello-agent health endpoint returns a successful HTTP response
- [x] 5.4 Verify core-to-agent connectivity via cluster DNS

**Acceptance checks**
- Hello-agent pod reaches `Ready` state
- Hello-agent health endpoint returns a successful response
- Request from core pod to `http://hello-agent:80/` succeeds over cluster networking

## 6. Expose and Document Operator Access
- [x] 6.1 Document `kubectl port-forward` commands for each service (core, postgres, redis, rabbitmq, opensearch)
- [x] 6.2 Document optional Traefik Ingress path for hostname-based routing
- [x] 6.3 Document known gaps and temporary workarounds (services not yet exposed, etc.)

**Acceptance checks**
- `kubectl port-forward svc/core 8081:80 -n brama` makes `http://localhost:8081/health` accessible
- All documented port-forward commands work on Rancher Desktop without undocumented manual steps

## 7. Documentation
- [x] 7.1 Create k3s local setup runbook in `docs/` (English, developer-facing)
- [x] 7.2 Document the ConfigMap/Secret mapping strategy with rationale
- [x] 7.3 Document the relationship between this local k3s path and the Docker Compose runtime
- [x] 7.4 Update `docs/agent-requirements/` if agent deployment conventions changed

**Acceptance checks**
- Runbook covers prerequisites, namespace setup, config, infra boot, core boot, agent boot, and access
- A new developer can follow the runbook end-to-end without undocumented steps
