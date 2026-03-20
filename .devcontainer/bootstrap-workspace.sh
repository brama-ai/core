#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BOOTSTRAP_DIR="${SCRIPT_DIR}/bootstrap"

run_job() {
  local name="$1"
  shift

  (
    "$@"
  ) &
  echo "$! ${name}"
}

wait_for_jobs() {
  local failures=0
  local job pid name

  for job in "$@"; do
    pid="${job%% *}"
    name="${job#* }"

    if wait "${pid}"; then
      echo "[OK]   ${name}"
    else
      echo "[FAIL] ${name}"
      failures=1
    fi
  done

  return "${failures}"
}

echo "==> Starting workspace bootstrap..."

job_opencode="$(run_job "OpenCode setup" "${BOOTSTRAP_DIR}/install-opencode.sh")"
job_php="$(run_job "PHP dependencies" "${BOOTSTRAP_DIR}/install-php-deps.sh")"
job_node="$(run_job "Node dependencies" "${BOOTSTRAP_DIR}/install-node-deps.sh")"
job_e2e="$(run_job "E2E dependencies" "${BOOTSTRAP_DIR}/install-e2e-deps.sh")"

wait_for_jobs "${job_opencode}" "${job_php}" "${job_node}" "${job_e2e}"

"${BOOTSTRAP_DIR}/run-migrations.sh"

echo "==> Workspace bootstrap complete."
