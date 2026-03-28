#!/usr/bin/env bash
# deploy/build-and-push.sh
#
# Build all platform Docker images and push them to the local k3s registry.
#
# Usage:
#   ./deploy/build-and-push.sh [service1 service2 ...]
#
# If no services are specified, all 7 platform images are built.
# Run from the workspace root (brama-core/ parent directory).
#
# Prerequisites:
#   - Docker installed and running
#   - Local registry running at registry.localhost:5000
#   - k3s registries.yaml configured to trust registry.localhost:5000
#
# Examples:
#   ./deploy/build-and-push.sh                          # build all
#   ./deploy/build-and-push.sh core knowledge-agent     # build specific services
#   VERSION=1.2.3 ./deploy/build-and-push.sh            # pin a version tag

set -euo pipefail

REGISTRY="${REGISTRY:-registry.localhost:5000}"
NAMESPACE="${NAMESPACE:-acp}"
VERSION="${VERSION:-latest}"
WORKSPACE_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

# ---------------------------------------------------------------------------
# Service definitions: name → Dockerfile path (relative to workspace root)
# ---------------------------------------------------------------------------
declare -A DOCKERFILES=(
  [core]="brama-core/docker/brama-core/Dockerfile"
  [knowledge-agent]="brama-core/docker/knowledge-agent/Dockerfile"
  [hello-agent]="brama-core/docker/hello-agent/Dockerfile"
  [wiki-agent]="brama-core/docker/wiki-agent/Dockerfile"
  [news-maker-agent]="brama-core/docker/news-maker-agent/Dockerfile"
  [dev-reporter-agent]="brama-core/docker/dev-reporter-agent/Dockerfile"
  [dev-agent]="brama-core/docker/dev-agent/Dockerfile"
)

# Build context directories (relative to workspace root)
declare -A BUILD_CONTEXTS=(
  [core]="brama-core"
  [knowledge-agent]="brama-core"
  [hello-agent]="brama-core"
  [wiki-agent]="brama-core"
  [news-maker-agent]="brama-core"
  [dev-reporter-agent]="brama-core"
  [dev-agent]="brama-core"
)

# ---------------------------------------------------------------------------
# Determine which services to build
# ---------------------------------------------------------------------------
if [[ $# -gt 0 ]]; then
  SERVICES=("$@")
else
  SERVICES=(core knowledge-agent hello-agent wiki-agent news-maker-agent dev-reporter-agent dev-agent)
fi

# ---------------------------------------------------------------------------
# Build and push
# ---------------------------------------------------------------------------
FAILED=()
SUCCEEDED=()

echo "==> Registry: ${REGISTRY}/${NAMESPACE}"
echo "==> Version:  ${VERSION}"
echo "==> Services: ${SERVICES[*]}"
echo ""

for svc in "${SERVICES[@]}"; do
  dockerfile="${DOCKERFILES[$svc]:-}"
  context="${BUILD_CONTEXTS[$svc]:-}"

  if [[ -z "$dockerfile" ]]; then
    echo "[SKIP] Unknown service: $svc"
    continue
  fi

  dockerfile_path="${WORKSPACE_ROOT}/${dockerfile}"
  context_path="${WORKSPACE_ROOT}/${context}"
  image="${REGISTRY}/${NAMESPACE}/${svc}:${VERSION}"

  if [[ ! -f "$dockerfile_path" ]]; then
    echo "[WARN] Dockerfile not found: ${dockerfile_path} — skipping ${svc}"
    FAILED+=("$svc (Dockerfile not found)")
    continue
  fi

  echo "==> Building ${svc} → ${image}"
  if docker build \
    -f "${dockerfile_path}" \
    -t "${image}" \
    "${context_path}"; then
    echo "==> Pushing ${image}"
    if docker push "${image}"; then
      echo "[OK] ${svc} pushed successfully"
      SUCCEEDED+=("$svc")
    else
      echo "[FAIL] Push failed for ${svc}"
      FAILED+=("$svc (push failed)")
    fi
  else
    echo "[FAIL] Build failed for ${svc}"
    FAILED+=("$svc (build failed)")
  fi
  echo ""
done

# ---------------------------------------------------------------------------
# Summary
# ---------------------------------------------------------------------------
echo "==> Build summary"
echo "    Succeeded: ${#SUCCEEDED[@]} — ${SUCCEEDED[*]:-none}"
echo "    Failed:    ${#FAILED[@]} — ${FAILED[*]:-none}"

if [[ ${#FAILED[@]} -gt 0 ]]; then
  echo ""
  echo "[ERROR] One or more builds failed. Check output above."
  exit 1
fi

echo ""
echo "==> All images built and pushed successfully."
echo "    Verify with: curl -s http://${REGISTRY}/v2/_catalog"
