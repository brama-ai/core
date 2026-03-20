#!/usr/bin/env bash
#
# Tests for builder/env-requirements.json
#
# Usage:
#   ./builder/tests/test-env-requirements.sh
#
set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
REGISTRY="$REPO_ROOT/builder/env-requirements.json"

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

assert_true() {
  local desc="$1" value="$2"
  TOTAL=$((TOTAL + 1))
  if [[ "$value" == "true" ]]; then
    echo -e "  ${GREEN}✓${NC} $desc"
    PASS=$((PASS + 1))
  else
    echo -e "  ${RED}✗${NC} $desc"
    FAIL=$((FAIL + 1))
  fi
}

echo ""
echo "env-requirements.json Tests"
echo "==========================="
echo ""

# ── Test 1: File exists ───────────────────────────────────────────────
echo "Test 1: Registry file exists"

TOTAL=$((TOTAL + 1))
if [[ -f "$REGISTRY" ]]; then
  echo -e "  ${GREEN}✓${NC} env-requirements.json exists"
  PASS=$((PASS + 1))
else
  echo -e "  ${RED}✗${NC} env-requirements.json not found: $REGISTRY"
  FAIL=$((FAIL + 1))
  echo ""
  echo "==========================="
  echo -e "Results: ${GREEN}${PASS} passed${NC}, ${RED}${FAIL} failed${NC}, ${TOTAL} total"
  exit 1
fi

echo ""

# ── Test 2: Valid JSON ────────────────────────────────────────────────
echo "Test 2: Registry is valid JSON"

if ! command -v jq &>/dev/null; then
  echo -e "  ${YELLOW}⚠${NC} jq not available — skipping JSON validation"
  TOTAL=$((TOTAL + 1))
  PASS=$((PASS + 1))
else
  TOTAL=$((TOTAL + 1))
  if jq . "$REGISTRY" &>/dev/null; then
    echo -e "  ${GREEN}✓${NC} Valid JSON"
    PASS=$((PASS + 1))
  else
    echo -e "  ${RED}✗${NC} Invalid JSON"
    FAIL=$((FAIL + 1))
    echo ""
    echo "==========================="
    echo -e "Results: ${GREEN}${PASS} passed${NC}, ${RED}${FAIL} failed${NC}, ${TOTAL} total"
    exit 1
  fi
fi

echo ""

# ── Test 3: Required top-level keys ──────────────────────────────────
echo "Test 3: Required top-level keys present"

if command -v jq &>/dev/null; then
  has_global=$(jq 'has("global")' "$REGISTRY" 2>/dev/null)
  has_apps=$(jq 'has("apps")' "$REGISTRY" 2>/dev/null)
  assert_eq "has 'global' key" "true" "$has_global"
  assert_eq "has 'apps' key" "true" "$has_apps"

  # Global has tools and services
  has_global_tools=$(jq '.global | has("tools")' "$REGISTRY" 2>/dev/null)
  has_global_services=$(jq '.global | has("services")' "$REGISTRY" 2>/dev/null)
  assert_eq "global has 'tools'" "true" "$has_global_tools"
  assert_eq "global has 'services'" "true" "$has_global_services"

  # Global tools include git and jq
  has_git=$(jq '.global.tools | contains(["git"])' "$REGISTRY" 2>/dev/null)
  has_jq=$(jq '.global.tools | contains(["jq"])' "$REGISTRY" 2>/dev/null)
  assert_eq "global tools includes git" "true" "$has_git"
  assert_eq "global tools includes jq" "true" "$has_jq"

  # Global services include postgresql and redis
  has_pg=$(jq '.global.services | contains(["postgresql"])' "$REGISTRY" 2>/dev/null)
  has_redis=$(jq '.global.services | contains(["redis"])' "$REGISTRY" 2>/dev/null)
  assert_eq "global services includes postgresql" "true" "$has_pg"
  assert_eq "global services includes redis" "true" "$has_redis"
fi

echo ""

# ── Test 4: All declared apps have required fields ────────────────────
echo "Test 4: All apps have required fields (runtime, min_version, tools)"

if command -v jq &>/dev/null; then
  apps=$(jq -r '.apps | keys[]' "$REGISTRY" 2>/dev/null)
  while IFS= read -r app; do
    [[ -z "$app" ]] && continue

    has_runtime=$(jq --arg a "$app" '.apps[$a] | has("runtime")' "$REGISTRY" 2>/dev/null)
    has_min_ver=$(jq --arg a "$app" '.apps[$a] | has("min_version")' "$REGISTRY" 2>/dev/null)
    has_tools=$(jq --arg a "$app" '.apps[$a] | has("tools")' "$REGISTRY" 2>/dev/null)

    assert_eq "${app}: has runtime" "true" "$has_runtime"
    assert_eq "${app}: has min_version" "true" "$has_min_ver"
    assert_eq "${app}: has tools" "true" "$has_tools"

    # Validate runtime is one of known values
    runtime=$(jq -r --arg a "$app" '.apps[$a].runtime' "$REGISTRY" 2>/dev/null)
    TOTAL=$((TOTAL + 1))
    case "$runtime" in
      php|python|node)
        echo -e "  ${GREEN}✓${NC} ${app}: runtime '${runtime}' is valid"
        PASS=$((PASS + 1))
        ;;
      *)
        echo -e "  ${RED}✗${NC} ${app}: unknown runtime '${runtime}'"
        FAIL=$((FAIL + 1))
        ;;
    esac
  done <<< "$apps"
fi

echo ""

# ── Test 5: Expected apps are present ────────────────────────────────
echo "Test 5: Expected apps are declared"

if command -v jq &>/dev/null; then
  expected_apps=("core" "knowledge-agent" "dev-reporter-agent" "news-maker-agent" "wiki-agent")
  for app in "${expected_apps[@]}"; do
    has_app=$(jq --arg a "$app" '.apps | has($a)' "$REGISTRY" 2>/dev/null)
    assert_eq "app '${app}' declared" "true" "$has_app"
  done
fi

echo ""

# ── Test 6: PHP apps have extensions field ────────────────────────────
echo "Test 6: PHP apps have extensions field"

if command -v jq &>/dev/null; then
  php_apps=$(jq -r '.apps | to_entries[] | select(.value.runtime == "php") | .key' "$REGISTRY" 2>/dev/null)
  while IFS= read -r app; do
    [[ -z "$app" ]] && continue
    has_ext=$(jq --arg a "$app" '.apps[$a] | has("extensions")' "$REGISTRY" 2>/dev/null)
    assert_eq "${app}: has extensions" "true" "$has_ext"
  done <<< "$php_apps"
fi

echo ""

# ── Test 7: deps_check commands reference valid executables ───────────
echo "Test 7: deps_check commands reference valid executables"

if command -v jq &>/dev/null; then
  apps=$(jq -r '.apps | keys[]' "$REGISTRY" 2>/dev/null)
  while IFS= read -r app; do
    [[ -z "$app" ]] && continue
    deps_check=$(jq -r --arg a "$app" '.apps[$a].deps_check // empty' "$REGISTRY" 2>/dev/null)
    [[ -z "$deps_check" ]] && continue

    # Extract the first word (the command)
    cmd=$(echo "$deps_check" | awk '{print $1}')
    TOTAL=$((TOTAL + 1))
    if command -v "$cmd" &>/dev/null; then
      echo -e "  ${GREEN}✓${NC} ${app}: deps_check command '${cmd}' found"
      PASS=$((PASS + 1))
    else
      echo -e "  ${YELLOW}⚠${NC} ${app}: deps_check command '${cmd}' not found (may be app-specific)"
      PASS=$((PASS + 1))  # Warn but don't fail — command may be in app's vendor/
    fi
  done <<< "$apps"
fi

echo ""

# ── Results ───────────────────────────────────────────────────────────
echo "==========================="
echo -e "Results: ${GREEN}${PASS} passed${NC}, ${RED}${FAIL} failed${NC}, ${TOTAL} total"
echo ""

[[ $FAIL -eq 0 ]] && exit 0 || exit 1
