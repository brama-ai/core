# Implementation Tasks

## 1. Validate Cluster Readiness
- [x] 1.1 Confirm the Rancher Desktop k3s cluster is reachable
- [x] 1.2 Confirm the target namespace exists
- [x] 1.3 Confirm no critical system pods are failing

**Acceptance checks**
- `kubectl get nodes` shows all nodes `Ready`
- `kubectl get pods -A` shows no critical system pod in `CrashLoopBackOff`

## 2. Validate Infrastructure Layer
- [x] 2.1 Validate PostgreSQL readiness
- [x] 2.2 Validate Redis readiness
- [x] 2.3 Validate RabbitMQ readiness
- [x] 2.4 Validate OpenSearch readiness (skipped — disabled in values-k3s-dev.yaml; uses Docker Compose OpenSearch)

**Acceptance checks**
- Each infra service has a documented readiness signal ✓
- Each failing service has a documented inspection command such as `kubectl logs` or `kubectl describe pod` ✓

## 3. Validate Core Runtime
- [x] 3.1 Validate core pod readiness
- [x] 3.2 Validate core health endpoint from inside or outside the cluster
- [x] 3.3 Validate operator-facing access path such as ingress or port-forward

**Acceptance checks**
- Core health responds successfully ✓ (documented with exec + port-forward paths)
- Documented local access path works in a browser or with `curl` ✓

## 4. Validate Reference Agent Runtime
- [x] 4.1 Validate reference agent readiness (hello-agent)
- [x] 4.2 Validate reference agent health endpoint
- [x] 4.3 Validate core-to-agent connectivity or discovery

**Acceptance checks**
- The reference agent responds successfully on its health endpoint ✓
- There is evidence that core can reach the agent using the local k3s network path ✓ (cluster DNS + ai.platform.agent label verification)

## 5. Publish Verified Runbook
- [x] 5.1 Capture the exact step order that worked on Rancher Desktop
- [x] 5.2 Capture known issues and workarounds
- [x] 5.3 Capture the minimum command sequence for re-validation

**Acceptance checks**
- A new operator can follow the runbook without relying on undocumented tribal knowledge ✓
- The runbook includes both success criteria and failure inspection commands ✓

**Runbook location**: `docs/guides/deployment/en/local-k3s-validation.md` (ua: `ua/local-k3s-validation.md`)
