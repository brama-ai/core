# Local k3s Runtime Validation Runbook

## Overview

This runbook provides a reproducible 5-stage validation path that proves the local k3s runtime
works correctly with the current Helm charts and devcontainer configuration. Follow every stage
in order — each stage depends on the previous one passing.

**Target environment**: Rancher Desktop k3s (local development)  
**Helm chart**: `brama-core/deploy/charts/brama/`  
**Values file**: `values-k3s-dev.yaml`  
**Makefile targets**: `k8s-setup`, `k8s-build`, `k8s-load`, `k8s-secrets`, `k8s-deploy`, `k8s-status`

Ukrainian mirror: [`docs/guides/deployment/ua/local-k3s-validation.md`](../ua/local-k3s-validation.md)

---

## Prerequisites

Before starting validation, confirm the following are available:

- Rancher Desktop ≥ 1.12 with k3s enabled (not dockerd mode)
- `kubectl` configured and pointing to `rancher-desktop` context
- `helm` 3.12+ installed
- `rdctl` available in PATH (ships with Rancher Desktop)
- Devcontainer running with Docker-outside-of-Docker feature enabled
- Source code checked out at workspace root

Verify context before any step:

```bash
kubectl config current-context
# Expected: rancher-desktop
```

---

## Stage 1: Cluster Readiness

**Goal**: Confirm the k3s cluster is operational before any deployment proceeds.

### 1.1 — Confirm cluster node is reachable

```bash
kubectl get nodes
```

**Expected output** (at least one node in `Ready` state):

```
NAME                   STATUS   ROLES                  AGE   VERSION
rancher-desktop        Ready    control-plane,master   5d    v1.31.x+k3s1
```

**If the node is `NotReady`**: Restart Rancher Desktop and wait 60 seconds, then retry.

### 1.2 — Confirm target namespace exists

```bash
kubectl get ns brama
```

If the namespace does not exist, create it:

```bash
make k8s-ns
# or: kubectl create namespace brama
```

**Expected output**:

```
NAME    STATUS   AGE
brama   Active   1m
```

### 1.3 — Confirm no critical system pods are failing

```bash
kubectl get pods -n kube-system
```

**Expected**: All pods in `Running` or `Completed` state. No pod should be in `CrashLoopBackOff` or `Error`.

**Inspection command if a pod is failing**:

```bash
kubectl describe pod <pod-name> -n kube-system
kubectl logs <pod-name> -n kube-system
```

**Acceptance checks for Stage 1**:
- [ ] `kubectl get nodes` shows all nodes `Ready`
- [ ] `kubectl get pods -A | grep kube-system` shows no pods in `CrashLoopBackOff` or `Error`
- [ ] Namespace `brama` exists and is `Active`

---

## Stage 2: Infrastructure Layer

**Goal**: Confirm all in-cluster infrastructure services (PostgreSQL, Redis, RabbitMQ) are healthy.

> **OpenSearch note**: OpenSearch is **disabled** in `values-k3s-dev.yaml` (`opensearch.enabled: false`).
> The local k3s profile uses the Docker Compose OpenSearch instance instead. No in-cluster
> OpenSearch validation is required for this profile.

### 2.1 — Validate PostgreSQL readiness

```bash
# Check pod status
kubectl get pods -n brama -l app.kubernetes.io/name=postgresql

# Exec into the pod and run a query
kubectl exec -n brama deploy/brama-postgresql -- \
  psql -U app -d ai_community_platform -c "SELECT 1;"
```

**Expected**: Pod is `Running 1/1`. Query returns:

```
 ?column?
----------
        1
(1 row)
```

**Inspection commands if failing**:

```bash
kubectl logs -n brama -l app.kubernetes.io/name=postgresql
kubectl describe pod -n brama -l app.kubernetes.io/name=postgresql
```

### 2.2 — Validate Redis readiness

```bash
# Check pod status
kubectl get pods -n brama -l app.kubernetes.io/name=redis

# Exec into the pod and ping
kubectl exec -n brama deploy/brama-redis-master -- redis-cli ping
```

**Expected**: Pod is `Running 1/1`. Command returns `PONG`.

**Inspection commands if failing**:

```bash
kubectl logs -n brama -l app.kubernetes.io/name=redis
kubectl describe pod -n brama -l app.kubernetes.io/name=redis
```

### 2.3 — Validate RabbitMQ readiness

```bash
# Check pod status
kubectl get pods -n brama -l app.kubernetes.io/name=rabbitmq

# Exec into the pod and check status
kubectl exec -n brama deploy/brama-rabbitmq -- rabbitmqctl status
```

**Expected**: Pod is `Running 1/1`. Output includes the RabbitMQ version line without errors.

**Inspection commands if failing**:

```bash
kubectl logs -n brama -l app.kubernetes.io/name=rabbitmq
kubectl describe pod -n brama -l app.kubernetes.io/name=rabbitmq
```

### 2.4 — OpenSearch (skipped — disabled in k3s-dev profile)

OpenSearch is intentionally disabled in `values-k3s-dev.yaml`:

```yaml
opensearch:
  enabled: false
```

**Rationale**: The local k3s profile is resource-constrained. OpenSearch requires significant
memory and is not needed for core platform validation. The Docker Compose stack provides
OpenSearch for development workflows that require it.

**Acceptance checks for Stage 2**:
- [ ] PostgreSQL pod is `Running 1/1` and `SELECT 1` succeeds
- [ ] Redis pod is `Running 1/1` and `redis-cli ping` returns `PONG`
- [ ] RabbitMQ pod is `Running 1/1` and `rabbitmqctl status` shows version without errors
- [ ] OpenSearch skip is documented (this item — `opensearch.enabled: false` in values file)

---

## Stage 3: Core Runtime

**Goal**: Confirm the core application pod is running, healthy, and accessible to operators.

### 3.1 — Validate core pod readiness

```bash
kubectl get pods -n brama -l app.kubernetes.io/component=core
```

**Expected**:

```
NAME                          READY   STATUS    RESTARTS   AGE
brama-core-7d9f8b6c4-xk2pq   1/1     Running   0          5m
```

Pod must show `READY 1/1` and `STATUS Running`.

**Inspection commands if failing**:

```bash
kubectl logs deploy/brama-core -n brama
kubectl logs deploy/brama-core -n brama --previous
kubectl describe pod -n brama -l app.kubernetes.io/component=core
```

### 3.2 — Validate core health endpoint via exec

```bash
CORE_POD=$(kubectl get pod -n brama -l app.kubernetes.io/component=core -o jsonpath='{.items[0].metadata.name}')
kubectl exec -n brama "$CORE_POD" -- curl -sf http://localhost/health
```

**Expected response**:

```json
{"status":"ok","timestamp":"2026-03-28T12:00:00+00:00"}
```

### 3.3 — Validate operator-facing access

#### Option A: Port-forward (always works, no `/etc/hosts` required)

```bash
kubectl port-forward -n brama svc/brama-core 8080:80 &
curl -sf http://localhost:8080/health
```

**Expected**: Same health response as above.

Kill the port-forward when done:

```bash
kill %1
```

#### Option B: Traefik ingress (requires `/etc/hosts` entry)

Add to `/etc/hosts`:

```
127.0.0.1 core.localhost
```

Then:

```bash
curl -sf http://core.localhost/health
```

**Expected**: Same health response.

**Verify ingress is configured**:

```bash
kubectl get ingress -n brama
# Expected: brama ingress with host core.localhost
```

**Acceptance checks for Stage 3**:
- [ ] Core pod shows `READY 1/1` and `STATUS Running`
- [ ] `curl http://localhost/health` via exec returns `{"status":"ok","timestamp":"..."}`
- [ ] Port-forward `svc/brama-core 8080:80` serves health endpoint on `localhost:8080`
- [ ] Traefik ingress routes `core.localhost` to core service (with `/etc/hosts` entry)

---

## Stage 4: Reference Agent Runtime

**Goal**: Confirm hello-agent is running, healthy, and discoverable by the core platform.

### 4.1 — Validate hello-agent pod readiness

```bash
kubectl get pods -n brama -l app.kubernetes.io/component=agent-hello
```

**Expected**:

```
NAME                                READY   STATUS    RESTARTS   AGE
brama-agent-hello-5f8d9c7b4-m3nqr   1/1     Running   0          5m
```

Pod must show `READY 1/1` and `STATUS Running`.

**Inspection commands if failing**:

```bash
kubectl logs deploy/brama-agent-hello -n brama
kubectl logs deploy/brama-agent-hello -n brama --previous
kubectl describe pod -n brama -l app.kubernetes.io/component=agent-hello
```

### 4.2 — Validate hello-agent health endpoint

```bash
HELLO_POD=$(kubectl get pod -n brama -l app.kubernetes.io/component=agent-hello -o jsonpath='{.items[0].metadata.name}')
kubectl exec -n brama "$HELLO_POD" -- curl -sf http://localhost/health
```

**Expected response**:

```json
{"status":"ok","service":"hello-agent"}
```

### 4.3 — Validate core-to-agent connectivity

#### Check Kubernetes discovery labels

```bash
kubectl get svc -n brama -l ai.platform.agent=true
```

**Expected**: `brama-agent-hello` is listed with label `ai.platform.agent=true`.

```bash
kubectl get svc brama-agent-hello -n brama --show-labels
# Expected labels include: ai.platform.agent=true, ai.platform.agent-name=hello-agent
```

#### Validate cluster DNS connectivity from core pod

```bash
CORE_POD=$(kubectl get pod -n brama -l app.kubernetes.io/component=core -o jsonpath='{.items[0].metadata.name}')
kubectl exec -n brama "$CORE_POD" -- \
  curl -sf http://brama-agent-hello.brama.svc.cluster.local/health
```

**Expected**: Hello-agent health response is returned from within the core pod.

**Acceptance checks for Stage 4**:
- [ ] Hello-agent pod shows `READY 1/1` and `STATUS Running`
- [ ] Hello-agent health responds with `{"status":"ok","service":"hello-agent"}` via exec
- [ ] `kubectl get svc -n brama -l ai.platform.agent=true` lists `brama-agent-hello`
- [ ] Core pod can reach `http://brama-agent-hello.brama.svc.cluster.local/health`

---

## Stage 5: Verified Runbook

**Goal**: Confirm the full validation path is reproducible and documented.

### 5.1 — Exact step order for Rancher Desktop

The verified deployment sequence for a clean Rancher Desktop environment:

1. **Start Rancher Desktop** with k3s enabled (not dockerd mode)
2. **Verify context**: `kubectl config current-context` → `rancher-desktop`
3. **Bootstrap**: `make k8s-setup` (runs build → load → secrets → deploy in order)
4. **Wait for pods**: `make k8s-status` — wait until all pods are `Running`
5. **Validate**: Follow Stages 1–4 of this runbook
6. **Access**: `make k8s-port-forward svc=core port=8080:80` then `curl http://localhost:8080/health`

### 5.2 — Known issues and workarounds

See the [Known Issues](#known-issues) section below.

### 5.3 — Minimum re-validation sequence (6-step quick check)

Run these six commands to confirm the local k3s runtime is verified after any change:

```bash
# Step 1: Cluster node is ready
kubectl get nodes | grep -q Ready && echo "✓ Node ready" || echo "✗ Node not ready"

# Step 2: Namespace exists
kubectl get ns brama -o name 2>/dev/null && echo "✓ Namespace exists" || echo "✗ Namespace missing"

# Step 3: Core pod is running
kubectl get pods -n brama -l app.kubernetes.io/component=core --no-headers | grep -q "1/1.*Running" && echo "✓ Core running" || echo "✗ Core not running"

# Step 4: Core health responds
CORE_POD=$(kubectl get pod -n brama -l app.kubernetes.io/component=core -o jsonpath='{.items[0].metadata.name}')
kubectl exec -n brama "$CORE_POD" -- curl -sf http://localhost/health | grep -q '"status":"ok"' && echo "✓ Core healthy" || echo "✗ Core unhealthy"

# Step 5: Hello-agent is running
kubectl get pods -n brama -l app.kubernetes.io/component=agent-hello --no-headers | grep -q "1/1.*Running" && echo "✓ Hello-agent running" || echo "✗ Hello-agent not running"

# Step 6: Agent is discoverable
kubectl get svc -n brama -l ai.platform.agent=true --no-headers | grep -q "brama-agent-hello" && echo "✓ Agent discoverable" || echo "✗ Agent not discoverable"
```

All six steps passing confirms the local k3s runtime is verified.

---

## Known Issues

| Symptom | Cause | Fix |
|---------|-------|-----|
| `connection refused` on `kubectl` commands | Wrong kubectl context or cluster not running | Run `kubectl config use-context rancher-desktop`; restart Rancher Desktop |
| Pod stuck in `Pending` | Insufficient cluster resources or missing PVC | Check `kubectl describe pod <name> -n brama`; increase Rancher Desktop memory allocation |
| `ImagePullBackOff` | Image not loaded into k3s containerd | Run `make k8s-load` to import images via `rdctl shell sudo k3s ctr images import -` |
| `CrashLoopBackOff` on core pod | Missing secret, wrong `DATABASE_URL`, or failed migration | Check `kubectl logs deploy/brama-core -n brama --previous`; verify `make k8s-secrets` ran |
| `exec format error` | Image built on wrong CPU architecture (e.g. ARM Mac → x86 k3s) | Build images on the same architecture as the k3s node; use `docker buildx build --platform linux/amd64` for cross-compile |
| `core.localhost` not resolving | Missing `/etc/hosts` entry | Add `127.0.0.1 core.localhost` to `/etc/hosts`; use port-forward as fallback |
| Wrong kubectl context | `kubectl` pointing to a different cluster | Run `kubectl config use-context rancher-desktop` |
| `make k8s-load` fails with `rdctl: command not found` | Rancher Desktop not installed or not in PATH | Install Rancher Desktop; ensure `rdctl` is in PATH |
| Migration job not completed | First `helm install` timed out before hook ran | Run `kubectl exec -n brama deploy/brama-core -- php bin/console doctrine:migrations:migrate --no-interaction` |
| `local-path` StorageClass missing | Rancher Desktop provisioner not running | Check `kubectl get storageclass`; restart Rancher Desktop |

---

## Full Deployment Reference

### Makefile targets

| Target | Purpose |
|--------|---------|
| `make k8s-ctx` | Show current cluster context |
| `make k8s-ns` | Create `brama` namespace |
| `make k8s-build` | Build local Docker images |
| `make k8s-load` | Import images into k3s containerd via `rdctl` |
| `make k8s-secrets` | Create `brama-core-secrets` in the `brama` namespace |
| `make k8s-deploy` | Run `helm upgrade --install` with `values-k3s-dev.yaml` |
| `make k8s-setup` | Full bootstrap: build + load + secrets + deploy |
| `make k8s-status` | Show pods, services, ingress, and Helm release |
| `make k8s-logs svc=core` | Tail logs for a service |
| `make k8s-shell svc=core` | Open a shell in a pod |
| `make k8s-port-forward svc=core port=8080:80` | Port-forward a service |
| `make k8s-destroy` | Remove the Helm release |

### Components deployed by `values-k3s-dev.yaml`

| Component | Type | Notes |
|-----------|------|-------|
| `brama-core` | Deployment | Main platform (PHP/Symfony) |
| `brama-core-scheduler` | Deployment | Background scheduler |
| `brama-migrate-N` | Job (hook) | Runs migrations on install/upgrade |
| `brama-agent-hello` | Deployment | Reference agent |
| `brama-agent-newsmaker` | Deployment | News maker agent |
| `brama-postgresql` | StatefulSet | Bitnami sub-chart, `local-path` PVC |
| `brama-redis-master` | StatefulSet | Bitnami sub-chart, `local-path` PVC |
| `brama-rabbitmq` | StatefulSet | Bitnami sub-chart, `local-path` PVC |
| Traefik ingress | Ingress | Routes `core.localhost` to core service |

### Secrets created by `make k8s-secrets`

The `k8s-secrets` target creates a `brama-core-secrets` secret in the `brama` namespace with:

- `APP_SECRET` — random hex
- `EDGE_AUTH_JWT_SECRET` — random hex
- `DATABASE_URL` — `postgresql://app:app@brama-postgresql:5432/ai_community_platform?serverVersion=16&charset=utf8`
- `REDIS_URL` — `redis://brama-redis-master:6379`
- `RABBITMQ_URL` — `amqp://app:app@brama-rabbitmq:5672`
- `POSTGRES_PROVISIONER_URL` — same as `DATABASE_URL`

> These are local development secrets only. Do not use this pattern for production.

---

## Related Documentation

- [Kubernetes Installation Guide](./kubernetes-install.md) — full install reference including remote k3s
- [Kubernetes Upgrade Runbook](./kubernetes-upgrade.md) — upgrading to a new release
- [Deployment Topology](./deployment-topology.md) — supported topologies and trade-offs
- [Docker Deployment Guide](./deployment.md) — Docker Compose path
