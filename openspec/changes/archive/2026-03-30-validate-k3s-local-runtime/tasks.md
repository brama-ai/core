# Implementation Tasks

## 1. Validate Cluster Readiness

- [x] 1.1 Confirm the Rancher Desktop k3s cluster is reachable (`kubectl get nodes` shows at least one node in `Ready` state)
- [x] 1.2 Confirm the target namespace exists (`kubectl get ns brama` or create via `make k8s-ns`)
- [x] 1.3 Confirm no critical system pods are failing (no `CrashLoopBackOff` or `Error` in `kube-system`)

**Acceptance checks**
- `kubectl get nodes` shows all nodes `Ready`
- `kubectl get pods -A | grep kube-system` shows no pods in `CrashLoopBackOff` or `Error`
- Namespace `brama` exists and is `Active`

## 2. Validate Infrastructure Layer

- [x] 2.1 Validate PostgreSQL readiness (pod running, `psql SELECT 1` succeeds)
- [x] 2.2 Validate Redis readiness (pod running, `redis-cli ping` returns `PONG`)
- [x] 2.3 Validate RabbitMQ readiness (pod running, `rabbitmqctl status` shows version)
- [x] 2.4 Validate OpenSearch status (document that it is disabled in `values-k3s-dev.yaml`; uses Docker Compose OpenSearch)

**Acceptance checks**
- Each infra service has a documented readiness signal (query, ping, or status command)
- Each failing service has a documented inspection command (`kubectl logs`, `kubectl describe pod`)
- OpenSearch skip is documented with rationale

## 3. Validate Core Runtime

- [x] 3.1 Validate core pod readiness (`kubectl get pods -n brama -l app.kubernetes.io/component=core` shows `Running 1/1`)
- [x] 3.2 Validate core health endpoint via `kubectl exec` (`curl -sf http://localhost/health` returns `{"status":"ok"}`)
- [x] 3.3 Validate operator-facing access path via port-forward or Traefik ingress (`core.localhost`)

**Acceptance checks**
- Core health responds with `{"status":"ok","timestamp":"..."}` via exec
- Port-forward `svc/brama-core 8080:80` serves health endpoint on `localhost:8080`
- Traefik ingress routes `core.localhost` to core service (with `/etc/hosts` entry)

## 4. Validate Reference Agent Runtime

- [x] 4.1 Validate hello-agent pod readiness (`kubectl get pods -n brama -l app.kubernetes.io/component=agent-hello` shows `Running 1/1`)
- [x] 4.2 Validate hello-agent health endpoint via `kubectl exec` (returns `{"status":"ok","service":"hello-agent"}`)
- [x] 4.3 Validate core-to-agent connectivity via cluster DNS and Kubernetes discovery labels (`ai.platform.agent=true`)

**Acceptance checks**
- Hello-agent health responds successfully on its health endpoint
- `kubectl get svc -n brama -l ai.platform.agent=true` lists `brama-agent-hello`
- Core pod can reach `http://brama-agent-hello.brama.svc.cluster.local/health`

## 5. Publish Verified Runbook

- [x] 5.1 Capture the exact step order that worked on Rancher Desktop (prerequisites, deploy, validate stages)
- [x] 5.2 Capture known issues and workarounds (ImagePullBackOff, exec format error, missing secrets, etc.)
- [x] 5.3 Capture the minimum command sequence for re-validation (6-step quick check)

**Acceptance checks**
- A new operator can follow the runbook without relying on undocumented tribal knowledge
- The runbook includes both success criteria and failure inspection commands
- Known issues table covers at least: connection refused, pending pods, ImagePullBackOff, CrashLoopBackOff, exec format error, missing hosts entry, wrong kubectl context

## 6. Documentation

- [x] 6.1 Create or update `docs/guides/deployment/en/local-k3s-validation.md` (English runbook)
- [x] 6.2 Create or update `docs/guides/deployment/ua/local-k3s-validation.md` (Ukrainian mirror)

**Acceptance checks**
- Both language versions exist and cover all 5 validation stages
- Runbook references correct Helm chart path, values file, and Makefile targets

**Runbook location**: `docs/guides/deployment/en/local-k3s-validation.md` (ua: `docs/guides/deployment/ua/local-k3s-validation.md`)
