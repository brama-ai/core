# Docker Upgrade Guide

This guide covers upgrading the AI Community Platform on a Docker self-hosted deployment.

## When to Use This Guide

Use this guide when the platform is deployed on one VM or host with the repository checked out
locally and started through the supported compose stack (`make up`).

## Upgrade Flow Overview

1. Pre-upgrade checklist (backup, review release notes)
2. Fetch the target revision
3. Review configuration changes
4. Rebuild and start the updated stack
5. Run migration steps
6. Refresh runtime discovery state
7. Verify service health
8. Run smoke verification

If verification fails at any step, follow the [Rollback Flow](#rollback-flow).

---

## Pre-Upgrade Checklist

### 1. Record the currently running revision

```bash
git rev-parse HEAD
git status --short
```

Confirm there are no uncommitted production-only edits that would be overwritten.

### 2. Back up state before upgrading

Back up the following before any upgrade:

**Databases:**

```bash
# Core platform database
docker compose exec postgres pg_dump -U app ai_community_platform > backup-core-$(date +%Y%m%d).sql

# Knowledge agent database (if installed)
docker compose exec postgres pg_dump -U knowledge_agent knowledge_agent > backup-knowledge-$(date +%Y%m%d).sql

# News maker agent database (if installed)
docker compose exec postgres pg_dump -U news_maker_agent news_maker_agent > backup-news-$(date +%Y%m%d).sql

# Dev reporter agent database (if installed)
docker compose exec postgres pg_dump -U dev_reporter_agent dev_reporter_agent > backup-dev-reporter-$(date +%Y%m%d).sql

# LiteLLM database
docker compose exec postgres pg_dump -U app litellm > backup-litellm-$(date +%Y%m%d).sql
```

**Configuration files:**

```bash
cp .env.local .env.local.bak
cp compose.override.yaml compose.override.yaml.bak 2>/dev/null || true
cp docker/openclaw/.env docker/openclaw/.env.bak 2>/dev/null || true
cp -r .local/openclaw/state/ .local/openclaw/state.bak/ 2>/dev/null || true
```

### 3. Review release notes

Check the release notes for:

- New required env vars
- Migration requirements
- Changed compose fragments
- Removed or renamed services
- New migration targets

### 4. Capture current service status

```bash
make ps
```

---

## Standard Upgrade Flow

### Step 1: Fetch the target revision

```bash
git fetch origin
git checkout <target-ref>
```

If you deploy from `main` directly:

```bash
git pull origin main
```

### Step 2: Review configuration changes

Check whether the new release changes:

- `.env.local.example` (new required variables)
- Compose files (new services, changed ports)
- Required secrets
- Domain settings in `compose.override.yaml`

If secrets or generated OpenClaw config changed, re-run bootstrap:

```bash
make bootstrap
```

Use this only when the release explicitly requires secret redistribution or regenerated state.

### Step 3: Rebuild and start the updated stack

For a full-stack upgrade:

```bash
make up
```

This runs `docker compose ... up --build -d` for the full supported bundle.

For a targeted single-service upgrade (only when the release notes confirm the change is isolated):

```bash
docker compose -f compose.yaml -f compose.core.yaml \
  $(for f in compose.agent-*.yaml compose.langfuse.yaml compose.openclaw.yaml compose.slides.yaml; do [ -f "$f" ] && echo -n "-f $f "; done) \
  up -d --build --no-deps <service-name>
```

### Step 4: Run migration steps

Run migrations for the services installed in this environment:

```bash
make litellm-db-init       # Idempotent — safe to re-run
make migrate               # Core platform
make knowledge-migrate     # Knowledge agent (if installed)
make dev-reporter-migrate  # Dev reporter agent (if installed)
make dev-agent-migrate     # Dev agent (if installed)
make news-migrate          # News maker agent (if installed)
```

Notes:

- Run only the migrations for services that are installed.
- `wiki-agent` has no dedicated migration target.
- If a release introduces a new migration target, it is listed in the release notes.

### Step 5: Refresh runtime discovery state

If the release changes agent manifests, labels, URLs, or adds/removes agents:

```bash
make agent-discover
```

### Step 6: Verify service health

Check container status:

```bash
make ps
```

Check health endpoints:

```bash
curl -sf http://localhost/health && echo "core OK"
curl -sf http://localhost:8083/health && echo "knowledge-agent OK"
curl -sf http://localhost:8085/health && echo "hello-agent OK"
```

Or check all at once:

```bash
for port in 80 8083 8084 8085 8087 8088 8090; do
  echo -n "Port $port: "
  curl -sf http://localhost:$port/health && echo "OK" || echo "FAIL"
done
```

Check logs for recently restarted services:

```bash
make logs-core
make logs-openclaw
make logs-litellm
```

Use `Ctrl+C` after confirming logs are stable.

### Step 7: Run smoke verification

Minimum recommended smoke checks:

- Open the main platform URL
- Open admin login
- Open Langfuse or OpenClaw if enabled
- Trigger one known-safe agent flow
- Verify scheduler or worker surfaces if the release touched them

If the environment supports it, run the project smoke suite:

```bash
make e2e-smoke
```

Use this only in environments prepared for the E2E stack.

---

## Rollback Flow

Rollback is safe only if the upgrade did not introduce irreversible schema or data migrations.
If a migration is not backward compatible, restore from backup instead of checking out the old
revision.

### Step 1: Return to the previous revision

```bash
git checkout <previous-ref>
```

### Step 2: Rebuild and restart the previous services

```bash
make up
```

### Step 3: Restore data if needed

If the failed upgrade changed schema incompatibly:

```bash
# Restore core database
docker compose exec -T postgres psql -U app ai_community_platform < backup-core-YYYYMMDD.sql

# Restore other databases as needed
```

If OpenClaw state or generated config was changed incompatibly:

```bash
cp docker/openclaw/.env.bak docker/openclaw/.env
cp -r .local/openclaw/state.bak/ .local/openclaw/state/
docker compose restart openclaw-gateway
```

### Step 4: Re-run health verification

```bash
make ps
curl -sf http://localhost/health && echo "core OK"
```

### Step 5: Document the failed upgrade

Record:

- Target revision attempted
- Step where the failure occurred
- Migration status at time of failure
- Services affected
- Rollback actions taken

---

## Verification Gates

The following checks are the standard release gates for a supported Docker upgrade:

| Gate | Command |
|------|---------|
| Migration success | No errors from `make migrate` and per-agent migrate targets |
| Core health | `curl -sf http://localhost/health` returns 200 |
| Critical worker health | `make ps` shows `core-scheduler` running |
| Public entrypoint health | Platform URL loads in browser |
| Optional smoke suite | `make e2e-smoke` (if E2E stack is available) |

---

## Expected Future Evolution

When the platform starts publishing versioned images, this upgrade flow will evolve to:

1. Update image tags in `compose.override.yaml` or the supported compose inputs
2. Run `docker compose pull`
3. Run explicit migration commands
4. Restart services in supported order
5. Verify health and roll back to previous pinned tags if required

See [Deployment Contract](./docker-deployment-contract.md) for the image versioning policy.
