#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"

# Fix ownership of volume-mounted dirs (Docker creates them as root)
sudo chown -R vscode:vscode /home/vscode/.claude 2>/dev/null || true

# Ensure Docker socket is accessible (DooD — Docker-outside-of-Docker)
if [ -S /var/run/docker.sock ]; then
  sudo chmod 666 /var/run/docker.sock 2>/dev/null || true
fi

echo "==> Waiting for infrastructure services..."
# Postgres and Redis are guaranteed by depends_on in docker-compose.yml,
# but wait for DNS resolution inside the container
for i in $(seq 1 30); do
  PGPASSWORD=app pg_isready -h postgres -U app -d ai_community_platform -q 2>/dev/null && break
  echo "  waiting for postgres ($i/30)..."
  sleep 2
done

echo "==> Checking infrastructure services..."
check_service() {
  local name="$1" cmd="$2"
  if eval "$cmd" &>/dev/null; then
    echo "  [OK]   $name"
  else
    echo "  [FAIL] $name"
  fi
}
check_service "PostgreSQL"  "PGPASSWORD=app pg_isready -h postgres -U app -q"
check_service "Redis"       "redis-cli -h redis ping"
check_service "OpenSearch"  "curl -sf http://opensearch:9200"
check_service "RabbitMQ"    "curl -sf http://rabbitmq:15672"
check_service "LiteLLM"     "curl -sf http://litellm:4000/health/liveliness"
check_service "Traefik"     "curl -sf http://traefik:8080/api/rawdata"

echo ""
echo "==> Done! Devcontainer ready."
echo "  Workspace bootstrap is manual now to keep container startup fast."
echo "  Run: ${REPO_ROOT}/.devcontainer/bootstrap-workspace.sh"
echo "  Runtimes:"
echo "  - PHP:             $(php --version 2>/dev/null | head -1 || echo 'N/A')"
echo "  - Node:            $(node --version 2>/dev/null || echo 'N/A')"
echo "  - TypeScript:      $(tsc --version 2>/dev/null || echo 'N/A')"
echo "  - Go:              $(go version 2>/dev/null || echo 'N/A')"
echo "  - Bun:             $(bun --version 2>/dev/null || echo 'N/A')"
echo "  - Docker:          $(docker --version 2>/dev/null || echo 'N/A')"
echo "  - Composer:        $(composer --version 2>/dev/null | head -1 || echo 'N/A')"
echo "  Tools:"
echo "  - Claude Code:     $(claude --version 2>/dev/null || echo 'N/A')"
echo "  - OpenCode:        $(opencode --version 2>/dev/null || echo 'N/A')"
echo "  - tmux:            $(tmux -V 2>/dev/null || echo 'N/A')"
