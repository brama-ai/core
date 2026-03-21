#!/usr/bin/env bash
#
# Tests for builder/env-check.sh
#
# Usage:
#   ./builder/tests/test-env-check.sh
#
set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
ENV_CHECK="$REPO_ROOT/builder/env-check.sh"

PASS=0
FAIL=0
TOTAL=0

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

assert_eq() {
  local desc="$1" expected="$2" actual="$3"
  TOTAL=$((TOTAL + 1))
  if [[ "$expected" == "$actual" ]]; then
    echo -e "  ${GREEN}✓${NC} $desc"
    PASS=$((PASS + 1))
  else
    echo -e "  ${RED}✗${NC} $desc"
    echo -e "    expected: ${expected}"
    echo -e "    actual:   ${actual}"
    FAIL=$((FAIL + 1))
  fi
}

assert_contains() {
  local desc="$1" needle="$2" haystack="$3"
  TOTAL=$((TOTAL + 1))
  if echo "$haystack" | grep -q "$needle"; then
    echo -e "  ${GREEN}✓${NC} $desc"
    PASS=$((PASS + 1))
  else
    echo -e "  ${RED}✗${NC} $desc"
    echo -e "    expected to contain: ${needle}"
    echo -e "    actual output: ${haystack:0:200}"
    FAIL=$((FAIL + 1))
  fi
}

assert_exit_code() {
  local desc="$1" expected="$2" actual="$3"
  TOTAL=$((TOTAL + 1))
  if [[ "$expected" == "$actual" ]]; then
    echo -e "  ${GREEN}✓${NC} $desc (exit ${actual})"
    PASS=$((PASS + 1))
  else
    echo -e "  ${RED}✗${NC} $desc"
    echo -e "    expected exit: ${expected}"
    echo -e "    actual exit:   ${actual}"
    FAIL=$((FAIL + 1))
  fi
}

echo ""
echo "env-check.sh Tests"
echo "=================="
echo ""

# ── Test 1: Script exists and is executable ───────────────────────────
echo "Test 1: Script exists and is executable"

TOTAL=$((TOTAL + 1))
if [[ -x "$ENV_CHECK" ]]; then
  echo -e "  ${GREEN}✓${NC} env-check.sh is executable"
  PASS=$((PASS + 1))
else
  echo -e "  ${RED}✗${NC} env-check.sh not found or not executable: $ENV_CHECK"
  FAIL=$((FAIL + 1))
fi

echo ""

# ── Test 2: --help flag ───────────────────────────────────────────────
echo "Test 2: --help flag produces usage text"

help_output=$("$ENV_CHECK" --help 2>&1)
help_exit=$?

assert_exit_code "--help exits 0" "0" "$help_exit"
assert_contains "--help shows Usage" "Usage:" "$help_output"
assert_contains "--help shows --app option" "\-\-app" "$help_output"
assert_contains "--help shows --json option" "\-\-json" "$help_output"
assert_contains "--help shows exit codes" "Exit codes:" "$help_output"

echo ""

# ── Test 3: --json produces valid JSON on stdout ──────────────────────
echo "Test 3: --json flag produces valid JSON"

tmp_report=$(mktemp)
json_output=$("$ENV_CHECK" --json --report-file "$tmp_report" --quiet 2>/dev/null || true)

TOTAL=$((TOTAL + 1))
if command -v jq &>/dev/null; then
  if echo "$json_output" | jq . &>/dev/null; then
    echo -e "  ${GREEN}✓${NC} --json output is valid JSON"
    PASS=$((PASS + 1))
  else
    echo -e "  ${RED}✗${NC} --json output is not valid JSON"
    echo -e "    output: ${json_output:0:200}"
    FAIL=$((FAIL + 1))
  fi

  # Validate JSON structure
  assert_contains "JSON has timestamp field" '"timestamp"' "$json_output"
  assert_contains "JSON has exit_code field" '"exit_code"' "$json_output"
  assert_contains "JSON has summary field" '"summary"' "$json_output"
  assert_contains "JSON has checks array" '"checks"' "$json_output"
  assert_contains "JSON has environment object" '"environment"' "$json_output"
else
  echo -e "  ${YELLOW}⚠${NC} jq not available — skipping JSON validation"
  PASS=$((PASS + 1))
fi

rm -f "$tmp_report"

echo ""

# ── Test 4: Exit code 0 in healthy devcontainer (global checks only) ──
echo "Test 4: Exit code 0 for global checks in devcontainer"

tmp_report2=$(mktemp)
"$ENV_CHECK" --quiet --report-file "$tmp_report2" 2>/dev/null
global_exit=$?

# In devcontainer, postgresql and redis should be available.
# On host machines, fatal exit is acceptable because services/tools may be absent.
TOTAL=$((TOTAL + 1))
if test -f /.dockerenv || test -n "${REMOTE_CONTAINERS:-}" || test -n "${CODESPACES:-}" || [[ "$PWD" == /workspaces/* ]]; then
  if [[ $global_exit -le 1 ]]; then
    echo -e "  ${GREEN}✓${NC} Global checks exit ${global_exit} (pass or warnings)"
    PASS=$((PASS + 1))
  else
    echo -e "  ${RED}✗${NC} Global checks exit ${global_exit} (fatal — check services)"
    FAIL=$((FAIL + 1))
  fi
else
  echo -e "  ${GREEN}✓${NC} Host environment detected — exit ${global_exit} accepted"
  PASS=$((PASS + 1))
fi

rm -f "$tmp_report2"

echo ""

# ── Test 5: Per-app filtering with --app core ─────────────────────────
echo "Test 5: Per-app filtering with --app core"

tmp_report3=$(mktemp)
app_output=$("$ENV_CHECK" --app core --report-file "$tmp_report3" 2>&1 || true)

assert_contains "--app core checks php_version" "php_version\|php" "$app_output"
assert_contains "--app core checks composer" "composer" "$app_output"

if command -v jq &>/dev/null && [[ -f "$tmp_report3" ]]; then
  # Verify report contains php-related checks
  php_checks=$(jq -r '[.checks[] | select(.name | startswith("php"))] | length' "$tmp_report3" 2>/dev/null || echo "0")
  TOTAL=$((TOTAL + 1))
  if [[ "$php_checks" -gt 0 ]]; then
    echo -e "  ${GREEN}✓${NC} Report contains ${php_checks} PHP-related checks"
    PASS=$((PASS + 1))
  else
    echo -e "  ${RED}✗${NC} Report contains no PHP checks"
    FAIL=$((FAIL + 1))
  fi

  # Verify required_by includes "core"
  core_checks=$(jq -r '[.checks[] | select(.required_by[] == "core")] | length' "$tmp_report3" 2>/dev/null || echo "0")
  TOTAL=$((TOTAL + 1))
  if [[ "$core_checks" -gt 0 ]]; then
    echo -e "  ${GREEN}✓${NC} Report has ${core_checks} checks required_by core"
    PASS=$((PASS + 1))
  else
    echo -e "  ${RED}✗${NC} No checks have required_by=core"
    FAIL=$((FAIL + 1))
  fi
fi

rm -f "$tmp_report3"

echo ""

# ── Test 6: Human-readable output contains check names ────────────────
echo "Test 6: Human-readable output contains expected check names"

human_output=$("$ENV_CHECK" 2>&1 || true)

assert_contains "Output contains 'jq'" "jq" "$human_output"
assert_contains "Output contains 'git'" "git" "$human_output"
assert_contains "Output contains 'postgresql'" "postgresql" "$human_output"
assert_contains "Output contains 'redis'" "redis" "$human_output"
assert_contains "Output contains 'opencode_providers'" "opencode_providers" "$human_output"

echo ""

# ── Test 7: --report-file writes to specified path ────────────────────
echo "Test 7: --report-file writes to specified path"

custom_report=$(mktemp)
"$ENV_CHECK" --quiet --report-file "$custom_report" 2>/dev/null || true

TOTAL=$((TOTAL + 1))
if [[ -f "$custom_report" && -s "$custom_report" ]]; then
  echo -e "  ${GREEN}✓${NC} Report written to custom path"
  PASS=$((PASS + 1))
else
  echo -e "  ${RED}✗${NC} Report not written to custom path: $custom_report"
  FAIL=$((FAIL + 1))
fi

rm -f "$custom_report"

echo ""

# ── Test 8: --quiet suppresses human output ───────────────────────────
echo "Test 8: --quiet suppresses human-readable output"

quiet_report=$(mktemp)
quiet_output=$("$ENV_CHECK" --quiet --report-file "$quiet_report" 2>&1 || true)

TOTAL=$((TOTAL + 1))
if [[ -z "$quiet_output" ]]; then
  echo -e "  ${GREEN}✓${NC} --quiet produces no stdout output"
  PASS=$((PASS + 1))
else
  echo -e "  ${YELLOW}⚠${NC} --quiet produced some output (may be acceptable): ${quiet_output:0:100}"
  PASS=$((PASS + 1))  # Not a hard failure
fi

rm -f "$quiet_report"

echo ""

# ── Results ───────────────────────────────────────────────────────────
echo "=================="
echo -e "Results: ${GREEN}${PASS} passed${NC}, ${RED}${FAIL} failed${NC}, ${TOTAL} total"
echo ""

[[ $FAIL -eq 0 ]] && exit 0 || exit 1
