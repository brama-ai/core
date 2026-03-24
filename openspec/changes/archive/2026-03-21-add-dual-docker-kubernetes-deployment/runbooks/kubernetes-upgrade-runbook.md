# Kubernetes Upgrade Runbook (Draft)

## Status

Draft target-state operator runbook for the future Kubernetes packaging.

- This runbook assumes the platform will ship an official Helm chart.
- The commands below describe the intended supported flow, not an already implemented chart path.
- Adjust chart path, release name, and workload names once the Kubernetes packaging is introduced.

## Intended Packaging Assumptions

- Packaging format: `Helm`
- Release name example: `ai-community-platform`
- Namespace example: `acp`
- Values file example: `values-prod.yaml`
- Stateful dependencies may be:
  - bundled in-cluster
  - referenced as external managed services

## When to Use

Use this runbook when upgrading a Kubernetes-based installation managed through the official chart.

## Pre-Upgrade Checklist

1. Record the current release state:

```bash
helm list -n acp
helm history ai-community-platform -n acp
kubectl get pods -n acp
```

2. Review the target release notes for:
   - chart version
   - app version
   - required values changes
   - new secrets
   - migration warnings
   - probe or ingress changes

3. Diff the current and target values:

```bash
helm get values ai-community-platform -n acp -o yaml > current-values.yaml
```

Compare `current-values.yaml` with the new release values file before applying the upgrade.

4. Confirm backup coverage:
   - PostgreSQL
   - Redis or queue state if relevant
   - object storage if used
   - external secret sources or sealed-secret inputs

5. Confirm cluster capacity and workload health before upgrading:

```bash
kubectl get deploy,statefulset,job -n acp
kubectl top pods -n acp
```

## Standard Upgrade Flow

### 1. Prepare the chart and values

If the chart is vendored in the repository:

```bash
helm dependency update ./deploy/charts/ai-community-platform
```

If the chart is published remotely, pull the target chart version or update the repo index before
continuing.

### 2. Run a preflight diff

If the Helm diff plugin is available:

```bash
helm diff upgrade ai-community-platform ./deploy/charts/ai-community-platform \
  -n acp \
  -f values-prod.yaml
```

At minimum, review:

- image tag changes
- secret references
- ingress host changes
- PVC changes
- migration job changes

### 3. Apply the upgrade

```bash
helm upgrade --install ai-community-platform ./deploy/charts/ai-community-platform \
  -n acp \
  --create-namespace \
  -f values-prod.yaml \
  --wait \
  --timeout 15m
```

Recommended future policy:

- pin chart version explicitly
- pin application image tags explicitly
- avoid floating `latest` tags in production values

### 4. Observe migration and hook jobs

If the chart uses upgrade hooks or dedicated migration jobs, confirm they completed:

```bash
kubectl get jobs -n acp
kubectl logs job/<migration-job-name> -n acp
```

If the migration job fails:

- do not continue traffic validation as if the upgrade succeeded
- determine whether the schema is partially applied
- decide between forward-fix and rollback based on migration reversibility

### 5. Observe rollout status

Check the key workloads:

```bash
kubectl rollout status deploy/brama-core -n acp
kubectl rollout status deploy/openclaw-gateway -n acp
kubectl rollout status deploy/litellm -n acp
```

If agent workloads are chart-managed, verify them too:

```bash
kubectl rollout status deploy/knowledge-agent -n acp
kubectl rollout status deploy/dev-reporter-agent -n acp
```

Actual workload names should match the chart output once implemented.

### 6. Run post-upgrade verification

Minimum verification:

- core health endpoint is healthy
- ingress routes resolve correctly
- admin login works
- one critical agent flow works
- observability surfaces load if enabled

Useful commands:

```bash
kubectl get ingress -n acp
kubectl get pods -n acp
kubectl logs deploy/brama-core -n acp --tail=100
```

If there is a smoke test job or external synthetic check, run it here as part of the standard
release gate.

## Rollback Flow

Rollback is not automatically safe if the failed release applied irreversible schema or data
transformations. Always evaluate the migration behavior before rolling back workloads.

### 1. Inspect release history

```bash
helm history ai-community-platform -n acp
```

Choose the last known-good revision.

### 2. Roll back the Helm release

```bash
helm rollback ai-community-platform <revision> -n acp --wait --timeout 15m
```

### 3. Verify rollout after rollback

```bash
kubectl get pods -n acp
kubectl rollout status deploy/brama-core -n acp
```

### 4. Restore data if rollback is not schema-compatible

If the failed release changed schema or data in a non-reversible way:

- restore the affected databases from backup
- restore any changed stateful stores required by the release
- re-run health verification only after restore is complete

## Failure Cases to Treat Explicitly

- migration job failed before app rollout
- app rollout succeeded but readiness probes fail
- rollout succeeded but ingress or external auth is broken
- one worker or scheduler failed while web surfaces looked healthy
- agent manifest or discovery compatibility regressed after the upgrade

## Expected Future Evolution

Once Kubernetes packaging is implemented, this draft should be tightened into an operator guide
with:

1. actual chart path or chart repository URL
2. actual workload names
3. actual migration job names and hook behavior
4. a supported `values.yaml` schema reference
5. explicit smoke commands or synthetic checks
