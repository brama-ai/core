# Docker Troubleshooting

Common issues and resolution steps for the Docker self-hosted deployment.

## Diagnostics

### Check service status

```bash
make ps
```

### Check logs for a specific service

```bash
docker compose logs <service-name> --tail 50
```

Common service names: `core`, `core-scheduler`, `postgres`, `redis`, `rabbitmq`, `litellm`,
`openclaw-gateway`, `langfuse-web`, `langfuse-worker`.

### Check all health endpoints

```bash
for port in 80 8083 8084 8085 8087 8088 8090; do
  echo -n "Port $port: "
  curl -sf http://localhost:$port/health && echo "OK" || echo "FAIL"
done
```

---

## Service Won't Start

```bash
docker compose logs <service-name> --tail 50
```

Common causes:

- Missing or incorrect env var — check `.env.local` and `compose.override.yaml`
- Port conflict — check if another process is using the port: `ss -tlnp | grep <port>`
- Dependency not healthy — check `make ps` for unhealthy infrastructure services

---

## Database Connection Issues

```bash
# Check if postgres is healthy
docker compose exec postgres pg_isready -U app -d ai_community_platform

# Test a query
docker compose exec postgres psql -U app -d ai_community_platform -c "SELECT 1"
```

If postgres is not running:

```bash
docker compose up -d postgres
```

Wait for it to become healthy, then restart the affected service:

```bash
docker compose restart core
```

---

## LiteLLM "Not connected to DB" / "Authentication Error"

This error on `http://localhost:4000/ui/login` means LiteLLM cannot use Postgres DB metadata.

```bash
make litellm-db-init
docker compose restart litellm
docker compose logs --tail=50 litellm
```

---

## OpenClaw Not Responding to Telegram

```bash
docker compose logs openclaw-gateway --tail 30
```

Check if the webhook is set:

```bash
docker compose exec openclaw-cli openclaw channels status
```

If the gateway token is wrong or missing:

```bash
# Regenerate and apply
make bootstrap
docker compose restart openclaw-gateway
```

---

## Migration Failures

If a migration command fails:

```bash
# Check the migration status
docker compose exec core php bin/console doctrine:migrations:status

# Check logs
docker compose logs core --tail 50
```

If the database schema is out of sync after a failed upgrade, restore from backup:

```bash
docker compose exec -T postgres psql -U app ai_community_platform \
  < backup-core-YYYYMMDD.sql
```

See [Docker Backup and Restore](./docker-backup-restore.md).

---

## Agent Not Appearing in Platform

If an agent is running but not visible in the platform:

```bash
# Re-run agent discovery
make agent-discover

# Check agent health
curl -sf http://localhost:8083/health    # knowledge-agent
curl -sf http://localhost:8085/health    # hello-agent
```

---

## Langfuse Not Loading

```bash
docker compose logs langfuse-web --tail 30
docker compose logs langfuse-worker --tail 30
```

If Langfuse services are not running:

```bash
make up-observability
```

If Langfuse data volumes are corrupted, see the volume reset procedure in
[docs/local-dev.md](../../../local-dev.md#2-langfuse-adminlocaldev--test-password).

---

## OpenSearch Issues

Check OpenSearch health:

```bash
curl -s http://localhost:9200/_cluster/health | jq .status
```

If OpenSearch is not starting due to memory limits:

```bash
# Check current vm.max_map_count
sysctl vm.max_map_count

# Set it (required for OpenSearch)
sysctl -w vm.max_map_count=262144

# Make it persistent
echo "vm.max_map_count=262144" >> /etc/sysctl.conf
```

---

## RabbitMQ Issues

```bash
docker compose exec rabbitmq rabbitmq-diagnostics -q ping
docker compose logs rabbitmq --tail 30
```

---

## Traefik Routing Issues

Check Traefik dashboard: `http://localhost:8080/dashboard/`

Check Traefik logs:

```bash
make logs-traefik
```

If a service is not routable, verify its Traefik labels in the compose file and confirm the
service is running and healthy.

---

## Stack Won't Start After Upgrade

If `make up` fails after an upgrade:

1. Check which service is failing: `make ps` and `docker compose logs <service>`
2. Verify the compose files are valid: `docker compose config`
3. If a new service was added, check if it requires new env vars in `.env.local`
4. If the issue is unresolvable, roll back: see [Docker Upgrade Guide](./docker-upgrade.md#rollback-flow)

---

## Useful Commands Reference

```bash
make ps                    # Service status
make logs                  # Follow all logs
make logs-core             # Core logs
make logs-openclaw         # OpenClaw gateway logs
make logs-langfuse         # Langfuse logs
make logs-litellm          # LiteLLM logs
make logs-traefik          # Traefik logs
make bootstrap             # Re-distribute secrets
make agent-discover        # Refresh agent registry
make litellm-db-init       # Fix LiteLLM DB
```
