# Docker Backup and Restore

This guide covers backup and restore procedures for the Docker self-hosted deployment.

## What to Back Up

| Asset | Location | Frequency |
|-------|----------|-----------|
| Core platform database | `postgres` container, `ai_community_platform` DB | Before every upgrade; daily in production |
| Knowledge agent database | `postgres` container, `knowledge_agent` DB | Before every upgrade |
| News maker agent database | `postgres` container, `news_maker_agent` DB | Before every upgrade |
| Dev reporter agent database | `postgres` container, `dev_reporter_agent` DB | Before every upgrade |
| LiteLLM database | `postgres` container, `litellm` DB | Before every upgrade |
| Langfuse databases | `langfuse-postgres` container | Before every upgrade (if Langfuse is enabled) |
| Secrets and config | `.env.local`, `compose.override.yaml`, `docker/openclaw/.env` | Before every upgrade |
| OpenClaw runtime state | `.local/openclaw/state/` | Before every upgrade (if OpenClaw is enabled) |

## Backup

### Databases

```bash
# Core platform
docker compose exec postgres pg_dump -U app ai_community_platform \
  > backup-core-$(date +%Y%m%d-%H%M).sql

# Knowledge agent (if installed)
docker compose exec postgres pg_dump -U knowledge_agent knowledge_agent \
  > backup-knowledge-$(date +%Y%m%d-%H%M).sql

# News maker agent (if installed)
docker compose exec postgres pg_dump -U news_maker_agent news_maker_agent \
  > backup-news-$(date +%Y%m%d-%H%M).sql

# Dev reporter agent (if installed)
docker compose exec postgres pg_dump -U dev_reporter_agent dev_reporter_agent \
  > backup-dev-reporter-$(date +%Y%m%d-%H%M).sql

# LiteLLM
docker compose exec postgres pg_dump -U app litellm \
  > backup-litellm-$(date +%Y%m%d-%H%M).sql
```

### Langfuse Databases (if Langfuse add-on is enabled)

```bash
docker compose exec langfuse-postgres pg_dump -U postgres postgres \
  > backup-langfuse-$(date +%Y%m%d-%H%M).sql
```

### Configuration Files

```bash
cp .env.local .env.local.bak
cp compose.override.yaml compose.override.yaml.bak 2>/dev/null || true
cp docker/openclaw/.env docker/openclaw/.env.bak 2>/dev/null || true
cp -r .local/openclaw/state/ .local/openclaw/state.bak/ 2>/dev/null || true
```

### Full Volume Backup (Alternative)

For a complete volume-level backup, stop the stack first:

```bash
make down

# Back up named volumes
docker run --rm \
  -v ai-community-platform_postgres-data:/data \
  -v $(pwd)/backups:/backup \
  alpine tar czf /backup/postgres-data-$(date +%Y%m%d).tar.gz -C /data .

docker run --rm \
  -v ai-community-platform_redis-data:/data \
  -v $(pwd)/backups:/backup \
  alpine tar czf /backup/redis-data-$(date +%Y%m%d).tar.gz -C /data .

make up
```

---

## Restore

### Restore a Database

```bash
# Stop the affected service first (optional but recommended)
docker compose stop core

# Restore core database
docker compose exec -T postgres psql -U app ai_community_platform \
  < backup-core-YYYYMMDD-HHMM.sql

# Restart the service
docker compose start core
```

Repeat for other databases as needed.

### Restore Configuration Files

```bash
cp .env.local.bak .env.local
cp compose.override.yaml.bak compose.override.yaml 2>/dev/null || true
cp docker/openclaw/.env.bak docker/openclaw/.env 2>/dev/null || true
cp -r .local/openclaw/state.bak/ .local/openclaw/state/ 2>/dev/null || true
```

After restoring OpenClaw config, restart the gateway:

```bash
docker compose restart openclaw-gateway
```

### Restore from Volume Backup

```bash
make down

docker run --rm \
  -v ai-community-platform_postgres-data:/data \
  -v $(pwd)/backups:/backup \
  alpine sh -c "rm -rf /data/* && tar xzf /backup/postgres-data-YYYYMMDD.tar.gz -C /data"

make up
```

---

## Verify After Restore

```bash
make ps

# Check core health
curl -sf http://localhost/health && echo "core OK"

# Verify database connectivity
docker compose exec postgres psql -U app -d ai_community_platform -c "SELECT 1"
```

---

## Backup Storage Recommendations

- Store backups off the deployment host (remote storage, S3-compatible bucket, or separate server)
- Retain at least the last 3 daily backups and the backup taken before each upgrade
- Test restore procedures periodically in a non-production environment
