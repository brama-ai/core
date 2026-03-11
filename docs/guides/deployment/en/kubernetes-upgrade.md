# Kubernetes Upgrade Runbook

## Overview

This runbook describes the supported upgrade flow for a Kubernetes-based installation managed
through the official Helm chart at `deploy/charts/ai-community-platform/`.

> **Status**: This runbook reflects the initial Helm chart skeleton. Workload names, migration job
> names, and chart repository details will be tightened as the packaging matures.

## When to Use

Use this runbook when:
- Upgrading to a new platform release on an existing Kubernetes installation
- Rolling back a failed upgrade
- Recovering from a partial migration failure

For a fresh install, see [`kubernetes-install.md`](./kubernetes-install.md).

## Pre-Upgrade Checklist

### 1. Record current release state

```bash
helm list -n acp
helm history ai-community-platform -n acp
kubectl get pods -n acp
```

Note the current revision number — you will need it for rollback.

### 2. Review the target release notes

Before upgrading, check:
- Chart version and app version changes
- New or changed `values.yaml` keys
- New required secrets
- Migration warnings or schema changes
- Probe or ingress changes

### 3. Diff current and target values

```bash
helm get values ai-community-platform -n acp -o yaml > current-values.yaml
```

Compare `current-values.yaml` with your `values-prod.yaml` and the new chart's
`values.yaml` to identify required changes.

If the Helm diff plugin is available:

```bash
helm diff upgrade ai-community-platform ./deploy/charts/ai-community-platform \
  -n acp \
  -f values-prod.yaml
```

Review:
- Image tag changes
- Secret reference changes
- Ingress host changes
- PVC changes
- Migration job changes

### 4. Confirm backup coverage

Before applying any upgrade, confirm you have current backups of:
- PostgreSQL databases (all databases used by core and agents)
- Redis state (if persistence is enabled)
- Any external secret sources

### 5. Confirm cluster health

```bash
kubectl get deploy,statefulset,job -n acp
kubectl top pods -n acp
```

Do not upgrade if existing workloads are unhealthy or if the cluster is under resource pressure.

## Standard Upgrade Flow

### 1. Update image tags in values

In your `values-prod.yaml`, update the image tags to the target release:

```yaml
core:
  image:
    tag: "0.2.0"  # target release

coreScheduler:
  image:
    tag: "0.2.0"

agents:
  knowledge:
    image:
      tag: "0.2.0"
  hello:
    image:
      tag: "0.2.0"

migrations:
  image:
    tag: "0.2.0"
```

### 2. Update chart dependencies (if needed)

If the chart has sub-chart dependency changes:

```bash
helm dependency update ./deploy/charts/ai-community-platform
```

### 3. Apply the upgrade

```bash
helm upgrade --install ai-community-platform \
  ./deploy/charts/ai-community-platform \
  --namespace acp \
  -f values-prod.yaml \
  --wait \
  --timeout 15m
```

The `--wait` flag causes Helm to wait until:
- The migration job completes (pre-upgrade hook)
- All Deployments reach their desired replica count
- All pods pass readiness probes

### 4. Observe migration job

The migration job runs as a `pre-upgrade` hook before new application pods start.

```bash
kubectl get jobs -n acp
kubectl logs job/ai-community-platform-migrate-<revision> -n acp
```

If the migration job fails:
- Do not proceed with traffic validation
- Determine whether the schema is partially applied
- Decide between forward-fix and rollback based on migration reversibility
- See the [Rollback Flow](#rollback-flow) section below

### 5. Observe rollout status

```bash
kubectl rollout status deploy/ai-community-platform-core -n acp
kubectl rollout status deploy/ai-community-platform-core-scheduler -n acp
```

For enabled agents:

```bash
kubectl rollout status deploy/ai-community-platform-agent-knowledge -n acp
kubectl rollout status deploy/ai-community-platform-agent-hello -n acp
```

### 6. Post-upgrade verification

Minimum verification gates:

- [ ] All pods are Running
- [ ] Migration job completed successfully
- [ ] Core health endpoint responds: `curl -sf https://platform.example.com/health`
- [ ] Ingress routes resolve correctly
- [ ] Admin login works
- [ ] At least one critical agent flow works

```bash
kubectl get pods -n acp
kubectl get ingress -n acp
kubectl logs deploy/ai-community-platform-core -n acp --tail=100
```

## Rollback Flow

> **Important**: Rollback is not automatically safe if the failed release applied irreversible
> schema or data transformations. Always evaluate migration behavior before rolling back workloads.

### 1. Inspect release history

```bash
helm history ai-community-platform -n acp
```

Identify the last known-good revision number.

### 2. Roll back the Helm release

```bash
helm rollback ai-community-platform <revision> -n acp --wait --timeout 15m
```

### 3. Verify rollout after rollback

```bash
kubectl get pods -n acp
kubectl rollout status deploy/ai-community-platform-core -n acp
curl -sf https://platform.example.com/health
```

### 4. Restore data if rollback is not schema-compatible

If the failed release changed schema or data in a non-reversible way:

1. Stop the application pods to prevent further writes:
   ```bash
   kubectl scale deploy/ai-community-platform-core -n acp --replicas=0
   ```
2. Restore the affected databases from the pre-upgrade backup
3. Scale the application back up:
   ```bash
   kubectl scale deploy/ai-community-platform-core -n acp --replicas=1
   ```
4. Re-run health verification

## Failure Cases

### Migration job failed before app rollout

- Check job logs for the specific error
- If the database is unreachable: fix connectivity, then re-run `helm upgrade`
- If the migration script failed: fix the migration, rebuild the image, re-run `helm upgrade`
- If schema is partially applied: assess reversibility before deciding to rollback

### App rollout succeeded but readiness probes fail

```bash
kubectl describe pod <pod-name> -n acp
kubectl logs <pod-name> -n acp
```

Common causes: wrong secret reference, missing env var, database connection failure.

### Rollout succeeded but ingress is broken

```bash
kubectl describe ingress ai-community-platform -n acp
kubectl get events -n acp --sort-by='.lastTimestamp'
```

### One worker or scheduler failed while web surfaces looked healthy

```bash
kubectl logs deploy/ai-community-platform-core-scheduler -n acp --tail=100
```

The scheduler runs as a single replica. If it is crash-looping, check for missing env vars or
database connectivity issues.

## Upgrade Verification Gates

The same logical gates apply to both Docker and Kubernetes upgrades:

| Gate | Kubernetes check |
|------|-----------------|
| Migration success | Migration job `Completed` |
| Core health | `GET /health` returns 200 |
| Critical worker health | Scheduler pod `Running` |
| Public entrypoint health | Ingress resolves and responds |
| Agent health (optional) | Agent `/health` endpoints respond |

## Related Runbooks

- [Install guide](./kubernetes-install.md)
- [Deployment topology matrix](./deployment-topology.md)
- [Docker upgrade runbook](./docker-upgrade.md)
