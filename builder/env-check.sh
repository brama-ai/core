#!/usr/bin/env bash
#
# Environment prerequisites checker for the builder pipeline.
# Validates runtimes, services, tools, and per-app dependencies.
#
# Usage: ./builder/env-check.sh [--app <name>] [--json] [--report-file <path>] [--quiet] [--help]
# Exit: 0=pass, 1=warnings, 2=fatal
#
set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
REGISTRY="$SCRIPT_DIR/env-requirements.json"
DEFAULT_REPORT="$REPO_ROOT/.opencode/pipeline/env-report.json"

# Colors (only when stdout is a terminal)
if [[ -t 1 ]]; then
  RED='\033[0;31m' GREEN='\033[0;32m' YELLOW='\033[1;33m'
  CYAN='\033[0;36m' BOLD='\033[1m' DIM='\033[2m' NC='\033[0m'
else
  RED='' GREEN='' YELLOW='' CYAN='' BOLD='' DIM='' NC=''
fi

# ── Argument parsing ──────────────────────────────────────────────────
APPS=()
JSON_MODE=false
QUIET=false
REPORT_FILE="$DEFAULT_REPORT"

show_help() {
  cat << 'HELP'
Usage: ./builder/env-check.sh [options]

Options:
  --app <name>          Check requirements for a specific app (repeatable)
                        Apps: core, knowledge-agent, dev-reporter-agent,
                              news-maker-agent, wiki-agent
  --json                Print JSON report to stdout
  --report-file <path>  Write JSON report to file (default: .opencode/pipeline/env-report.json)
  --quiet               Suppress human-readable output
  --help                Show this help

Exit codes:
  0  All checks passed
  1  Warnings only (pipeline may continue with degraded capability)
  2  Fatal failure (pipeline must cancel task)

Examples:
  ./builder/env-check.sh
  ./builder/env-check.sh --app core --app knowledge-agent
  ./builder/env-check.sh --json
  ./builder/env-check.sh --app news-maker-agent --report-file /tmp/env.json
HELP
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --app)          APPS+=("$2"); shift 2 ;;
    --json)         JSON_MODE=true; shift ;;
    --report-file)  REPORT_FILE="$2"; shift 2 ;;
    --quiet)        QUIET=true; shift ;;
    --help|-h)      show_help; exit 0 ;;
    *)              echo "Unknown option: $1" >&2; show_help >&2; exit 2 ;;
  esac
done

# ── State ─────────────────────────────────────────────────────────────
START_EPOCH=$(date +%s)
CHECKS_JSON=""
PASS_COUNT=0 WARN_COUNT=0 FAIL_COUNT=0
HAS_FATAL=false HAS_JQ=false

# Discovered versions
ENV_PHP="" ENV_PYTHON="" ENV_NODE="" ENV_COMPOSER=""
ENV_NPM="" ENV_PIP="" ENV_PG="" ENV_REDIS="" ENV_GIT=""

# ── Helpers ───────────────────────────────────────────────────────────

# Semver comparison: returns 0 if actual >= required (major.minor only)
semver_gte() {
  local actual="${1#v}" required="${2#v}"
  local am am2 rm rm2
  am=$(echo "$actual"  | cut -d. -f1); am2=$(echo "$actual"  | cut -d. -f2)
  rm=$(echo "$required" | cut -d. -f1); rm2=$(echo "$required" | cut -d. -f2)
  am="${am:-0}"; am2="${am2:-0}"; rm="${rm:-0}"; rm2="${rm2:-0}"
  (( am > rm )) && return 0
  (( am == rm && am2 >= rm2 )) && return 0
  return 1
}

json_escape() { local s="${1//\\/\\\\}"; printf '%s' "${s//\"/\\\"}"; }

record_check() {
  local name="$1" cat="$2" status="$3" detail="$4"; shift 4
  case "$status" in
    pass) PASS_COUNT=$((PASS_COUNT+1)) ;;
    warn) WARN_COUNT=$((WARN_COUNT+1)) ;;
    fail) FAIL_COUNT=$((FAIL_COUNT+1)); HAS_FATAL=true ;;
  esac
  local rb_json="[" first=true
  for rb in "${@+"$@"}"; do
    [[ "$first" == true ]] || rb_json+=","
    rb_json+="\"$(json_escape "$rb")\""
    first=false
  done
  rb_json+="]"
  local obj
  obj="{\"name\":\"$(json_escape "$name")\",\"category\":\"$(json_escape "$cat")\",\"status\":\"$(json_escape "$status")\",\"detail\":\"$(json_escape "$detail")\",\"required_by\":${rb_json}}"
  [[ -n "$CHECKS_JSON" ]] && CHECKS_JSON+=","
  CHECKS_JSON+="$obj"
}

pcheck() {
  [[ "$QUIET" == true ]] && return
  local status="$1" name="$2" detail="$3"
  # When --json is active, redirect human-readable output to stderr to keep stdout clean
  local out=1; [[ "$JSON_MODE" == true ]] && out=2
  case "$status" in
    pass) printf "  ${GREEN}✓${NC} %-30s %s\n" "$name" "$detail" >&$out ;;
    warn) printf "  ${YELLOW}⚠${NC} %-30s %s\n" "$name" "$detail" >&$out ;;
    fail) printf "  ${RED}✗${NC} %-30s %s\n" "$name" "$detail" >&$out ;;
  esac
}

# ── Individual checks ─────────────────────────────────────────────────

check_jq() {
  if command -v jq &>/dev/null; then
    HAS_JQ=true
    local ver; ver=$(jq --version 2>/dev/null | sed 's/jq-//')
    record_check "jq" "tool" "pass" "jq ${ver} available" "global"
    pcheck "pass" "jq" "${ver}"
  else
    record_check "jq" "tool" "warn" "jq not found — JSON report unavailable" "global"
    pcheck "warn" "jq" "not found (JSON report disabled)"
  fi
}

check_tool() {
  local tool="$1" required_by="${2:-global}"
  if command -v "$tool" &>/dev/null; then
    local ver; ver=$("$tool" --version 2>/dev/null | head -1 || echo "")
    record_check "$tool" "tool" "pass" "${tool} available: ${ver}" "$required_by"
    pcheck "pass" "$tool" "${ver}"
    [[ "$tool" == "git" ]] && ENV_GIT=$(git --version 2>/dev/null | awk '{print $3}')
  else
    record_check "$tool" "tool" "fail" "${tool} not found" "$required_by"
    pcheck "fail" "$tool" "not found"
  fi
}

check_postgresql() {
  if command -v pg_isready &>/dev/null && pg_isready -q 2>/dev/null; then
    local ver; ver=$(psql --version 2>/dev/null | awk '{print $3}' || echo "")
    [[ -n "$ver" ]] && ENV_PG="$ver"
    record_check "postgresql" "service" "pass" "PostgreSQL accepting connections${ver:+ (${ver})}" "global"
    pcheck "pass" "postgresql" "$(pg_isready 2>/dev/null || echo "accepting connections")"
  elif command -v pg_isready &>/dev/null; then
    record_check "postgresql" "service" "fail" "PostgreSQL not accepting connections" "global"
    pcheck "fail" "postgresql" "not accepting connections"
  else
    record_check "postgresql" "service" "warn" "pg_isready not found — cannot verify PostgreSQL" "global"
    pcheck "warn" "postgresql" "pg_isready not found"
  fi
}

check_redis() {
  if command -v redis-cli &>/dev/null; then
    local pong; pong=$(redis-cli ping 2>/dev/null || echo "")
    if [[ "$pong" == "PONG" ]]; then
      local ver; ver=$(redis-cli --version 2>/dev/null | awk '{print $2}' || echo "")
      [[ -n "$ver" ]] && ENV_REDIS="$ver"
      record_check "redis" "service" "pass" "Redis responding to PING${ver:+ (${ver})}" "global"
      pcheck "pass" "redis" "PONG${ver:+ (${ver})}"
    else
      record_check "redis" "service" "fail" "Redis not responding (got: ${pong:-no response})" "global"
      pcheck "fail" "redis" "not responding"
    fi
  else
    record_check "redis" "service" "warn" "redis-cli not found — cannot verify Redis" "global"
    pcheck "warn" "redis" "redis-cli not found"
  fi
}

check_runtime() {
  local runtime="$1" min_ver="$2" required_by="$3"
  local ver="" cmd=""
  case "$runtime" in
    php)
      command -v php &>/dev/null && ver=$(php -r 'echo PHP_VERSION;' 2>/dev/null || echo "")
      [[ -n "$ver" ]] && ENV_PHP="$ver"
      cmd="php_version"
      ;;
    python)
      if command -v python3 &>/dev/null; then cmd_bin="python3"
      elif command -v python &>/dev/null; then cmd_bin="python"; fi
      [[ -n "${cmd_bin:-}" ]] && ver=$("$cmd_bin" --version 2>&1 | awk '{print $2}' || echo "")
      [[ -n "$ver" ]] && ENV_PYTHON="$ver"
      cmd="python_version"
      ;;
    node)
      command -v node &>/dev/null && ver=$(node --version 2>/dev/null | sed 's/^v//')
      [[ -n "$ver" ]] && ENV_NODE="$ver"
      cmd="node_version"
      ;;
  esac

  if [[ -z "$ver" ]]; then
    record_check "$cmd" "runtime" "fail" "${runtime} not found" "$required_by"
    pcheck "fail" "$cmd" "not found"
    return 1
  fi
  if semver_gte "$ver" "$min_ver"; then
    record_check "$cmd" "runtime" "pass" "${runtime} ${ver} (>= ${min_ver} required)" "$required_by"
    pcheck "pass" "$cmd" "${ver} (>= ${min_ver})"
  else
    record_check "$cmd" "runtime" "fail" "${runtime} ${ver} < ${min_ver} required" "$required_by"
    pcheck "fail" "$cmd" "${ver} < ${min_ver} required"
    return 1
  fi
}

check_composer() {
  local required_by="${1:-global}"
  if command -v composer &>/dev/null; then
    local ver; ver=$(composer --version 2>/dev/null | awk '{print $3}' || echo "")
    ENV_COMPOSER="$ver"
    record_check "composer" "tool" "pass" "Composer ${ver} available" "$required_by"
    pcheck "pass" "composer" "${ver}"
  else
    record_check "composer" "tool" "fail" "Composer not found" "$required_by"
    pcheck "fail" "composer" "not found"
  fi
}

check_npm() {
  local required_by="${1:-global}"
  if command -v npm &>/dev/null; then
    local ver; ver=$(npm --version 2>/dev/null || echo "")
    ENV_NPM="$ver"
    record_check "npm" "tool" "pass" "npm ${ver} available" "$required_by"
    pcheck "pass" "npm" "${ver}"
  else
    record_check "npm" "tool" "fail" "npm not found" "$required_by"
    pcheck "fail" "npm" "not found"
  fi
}

check_pip() {
  local required_by="${1:-global}"
  local pip_cmd=""
  command -v pip3 &>/dev/null && pip_cmd="pip3"
  command -v pip  &>/dev/null && [[ -z "$pip_cmd" ]] && pip_cmd="pip"
  if [[ -n "$pip_cmd" ]]; then
    local ver; ver=$("$pip_cmd" --version 2>/dev/null | awk '{print $2}' || echo "")
    ENV_PIP="$ver"
    record_check "pip" "tool" "pass" "pip ${ver} available" "$required_by"
    pcheck "pass" "pip" "${ver}"
  else
    record_check "pip" "tool" "fail" "pip not found" "$required_by"
    pcheck "fail" "pip" "not found"
  fi
}

check_php_extensions() {
  local required_by="$1"; shift
  for ext in "${@+"$@"}"; do
    if php -m 2>/dev/null | grep -qi "^${ext}$"; then
      record_check "php_ext_${ext}" "extension" "pass" "PHP extension ${ext} loaded" "$required_by"
      pcheck "pass" "php_ext_${ext}" "loaded"
    else
      record_check "php_ext_${ext}" "extension" "fail" "PHP extension ${ext} not loaded" "$required_by"
      pcheck "fail" "php_ext_${ext}" "not loaded"
    fi
  done
}

check_deps() {
  local app="$1" deps_cmd="$2"
  [[ -z "$deps_cmd" ]] && return 0
  local run_dir="$REPO_ROOT/apps/${app}"
  if [[ ! -d "$run_dir" ]]; then
    record_check "deps_${app}" "deps" "warn" "App directory not found: ${run_dir}" "$app"
    pcheck "warn" "deps_${app}" "app dir not found"
    return 0
  fi
  local output exit_code=0
  output=$(cd "$run_dir" && eval "$deps_cmd" 2>&1) || exit_code=$?
  if [[ $exit_code -eq 0 ]]; then
    record_check "deps_${app}" "deps" "pass" "${deps_cmd} passed" "$app"
    pcheck "pass" "deps_${app}" "${deps_cmd}"
  else
    local first_line; first_line=$(echo "$output" | head -1)
    record_check "deps_${app}" "deps" "warn" "${deps_cmd} failed: ${first_line}" "$app"
    pcheck "warn" "deps_${app}" "${deps_cmd} failed"
  fi
}

# ── Global checks ─────────────────────────────────────────────────────
run_global_checks() {
  [[ "$QUIET" == false ]] && { [[ "$JSON_MODE" == true ]] && echo -e "\n${BOLD}Global checks${NC}" >&2 || echo -e "\n${BOLD}Global checks${NC}"; }
  check_jq
  check_tool "git" "global"
  check_postgresql
  check_redis
}

# ── Per-app checks ────────────────────────────────────────────────────
run_app_checks() {
  local app="$1"
  if [[ "$HAS_JQ" != true || ! -f "$REGISTRY" ]]; then
    record_check "registry_${app}" "config" "warn" "jq unavailable — cannot parse registry for ${app}" "$app"
    pcheck "warn" "registry_${app}" "jq required for per-app checks"
    return 0
  fi

  local runtime min_version deps_check
  runtime=$(jq -r ".apps[\"${app}\"].runtime // empty" "$REGISTRY" 2>/dev/null || echo "")
  min_version=$(jq -r ".apps[\"${app}\"].min_version // empty" "$REGISTRY" 2>/dev/null || echo "")
  deps_check=$(jq -r ".apps[\"${app}\"].deps_check // empty" "$REGISTRY" 2>/dev/null || echo "")

  if [[ -z "$runtime" ]]; then
    record_check "app_${app}" "config" "warn" "App '${app}' not found in env-requirements.json" "$app"
    pcheck "warn" "app_${app}" "not in registry"
    return 0
  fi

  [[ "$QUIET" == false ]] && { [[ "$JSON_MODE" == true ]] && echo -e "\n${BOLD}App: ${app} (${runtime} >= ${min_version})${NC}" >&2 || echo -e "\n${BOLD}App: ${app} (${runtime} >= ${min_version})${NC}"; }

  case "$runtime" in
    php)
      check_runtime "php" "$min_version" "$app"
      check_composer "$app"
      local exts=()
      while IFS= read -r ext; do [[ -n "$ext" ]] && exts+=("$ext"); done \
        < <(jq -r ".apps[\"${app}\"].extensions // [] | .[]" "$REGISTRY" 2>/dev/null)
      [[ ${#exts[@]} -gt 0 ]] && check_php_extensions "$app" "${exts[@]}"
      ;;
    python)
      check_runtime "python" "$min_version" "$app"
      check_pip "$app"
      ;;
    node)
      check_runtime "node" "$min_version" "$app"
      check_npm "$app"
      ;;
    *)
      record_check "runtime_${app}" "runtime" "warn" "Unknown runtime '${runtime}'" "$app"
      pcheck "warn" "runtime_${app}" "unknown: ${runtime}"
      ;;
  esac

  check_deps "$app" "$deps_check"
}

# ── JSON report ───────────────────────────────────────────────────────
write_json_report() {
  local exit_code="$1"
  local end_epoch; end_epoch=$(date +%s)
  local duration_ms=$(( (end_epoch - START_EPOCH) * 1000 ))
  local total=$((PASS_COUNT + WARN_COUNT + FAIL_COUNT))
  local ts; ts=$(date -u '+%Y-%m-%dT%H:%M:%SZ' 2>/dev/null || date '+%Y-%m-%dT%H:%M:%SZ')

  local summary
  if [[ $FAIL_COUNT -gt 0 ]]; then
    summary="${FAIL_COUNT} fatal failure(s), ${WARN_COUNT} warning(s), ${PASS_COUNT} passed of ${total} checks"
  elif [[ $WARN_COUNT -gt 0 ]]; then
    summary="${WARN_COUNT} warning(s), ${PASS_COUNT} passed of ${total} checks"
  else
    summary="All ${total} checks passed"
  fi

  # Build environment object
  local env_json="{" env_first=true
  _ef() {
    [[ -z "$2" ]] && return
    [[ "$env_first" == true ]] || env_json+=","
    env_json+="\"$(json_escape "$1")\":\"$(json_escape "$2")\""
    env_first=false
  }
  _ef "php" "$ENV_PHP"; _ef "python" "$ENV_PYTHON"; _ef "node" "$ENV_NODE"
  _ef "composer" "$ENV_COMPOSER"; _ef "npm" "$ENV_NPM"; _ef "pip" "$ENV_PIP"
  _ef "postgresql" "$ENV_PG"; _ef "redis" "$ENV_REDIS"; _ef "git" "$ENV_GIT"
  env_json+="}"

  local report
  report="{\"timestamp\":\"$(json_escape "$ts")\",\"exit_code\":${exit_code},\"summary\":\"$(json_escape "$summary")\",\"duration_ms\":${duration_ms},\"checks\":[${CHECKS_JSON}],\"environment\":${env_json}}"

  if [[ "$JSON_MODE" == true ]]; then
    [[ "$HAS_JQ" == true ]] && echo "$report" | jq . || echo "$report"
  fi

  if [[ -n "$REPORT_FILE" ]]; then
    mkdir -p "$(dirname "$REPORT_FILE")"
    if [[ "$HAS_JQ" == true ]]; then
      echo "$report" | jq . > "$REPORT_FILE" 2>/dev/null || echo "$report" > "$REPORT_FILE"
    else
      echo "$report" > "$REPORT_FILE"
    fi
  fi
}

# ── Summary ───────────────────────────────────────────────────────────
print_summary() {
  local exit_code="$1"
  [[ "$QUIET" == true ]] && return
  # When --json is active, redirect summary to stderr to keep stdout clean for JSON
  local _out=1; [[ "$JSON_MODE" == true ]] && _out=2
  local total=$((PASS_COUNT + WARN_COUNT + FAIL_COUNT))
  echo "" >&$_out
  echo -e "${BOLD}─────────────────────────────────────────${NC}" >&$_out
  if [[ $FAIL_COUNT -gt 0 ]]; then
    echo -e "${RED}${BOLD}FATAL: ${FAIL_COUNT} check(s) failed${NC} | ${YELLOW}${WARN_COUNT} warning(s)${NC} | ${GREEN}${PASS_COUNT} passed${NC} of ${total} total" >&$_out
    echo -e "${RED}Pipeline must be cancelled.${NC}" >&$_out
  elif [[ $WARN_COUNT -gt 0 ]]; then
    echo -e "${YELLOW}${BOLD}WARN: ${WARN_COUNT} warning(s)${NC} | ${GREEN}${PASS_COUNT} passed${NC} of ${total} total" >&$_out
    echo -e "${YELLOW}Pipeline may continue with degraded capability.${NC}" >&$_out
  else
    echo -e "${GREEN}${BOLD}All ${total} checks passed.${NC}" >&$_out
  fi
  [[ -n "$REPORT_FILE" && -f "$REPORT_FILE" ]] && echo -e "${DIM}Report: ${REPORT_FILE}${NC}" >&$_out
}

# ── Main ──────────────────────────────────────────────────────────────
main() {
  if [[ "$QUIET" == false && "$JSON_MODE" == false ]]; then
    echo -e "${CYAN}${BOLD}Environment Check${NC}"
  fi

  run_global_checks

  for app in "${APPS[@]+"${APPS[@]}"}"; do
    run_app_checks "$app"
  done

  local exit_code=0
  [[ "$HAS_FATAL" == true ]] && exit_code=2
  [[ "$HAS_FATAL" == false && $WARN_COUNT -gt 0 ]] && exit_code=1

  if [[ "$HAS_JQ" == true ]]; then
    write_json_report "$exit_code"
  else
    [[ "$QUIET" == false ]] && echo -e "\nNote: jq not available — JSON report skipped." >&2
  fi

  print_summary "$exit_code"
  exit "$exit_code"
}

main
