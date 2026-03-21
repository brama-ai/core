# Environment Prerequisites Checker

## Purpose

The `builder/env-check.sh` script validates environment prerequisites before any pipeline agent starts. It checks:

- **Global tools**: git, jq
- **Services**: PostgreSQL, Redis
- **OpenCode providers**: at least 2 configured providers visible in `opencode auth list`
- **Runtimes**: PHP (>= 8.5), Python (>= 3.12), Node (>= 20)
- **Package managers**: composer, npm, pip
- **PHP extensions**: json, mbstring, xml, pdo_pgsql, intl, curl (per-app configurable)
- **Per-app dependencies**: Configured in `builder/env-requirements.json`

## Usage

### Basic invocation

```bash
# Check global prerequisites only
./builder/env-check.sh

# Check prerequisites for a specific app
./builder/env-check.sh --app core

# Check prerequisites for multiple apps
./builder/env-check.sh --app core --app knowledge-agent
```

### Command-line flags

| Flag | Description |
|------|-------------|
| `--app <name>` | Check requirements for specific app (repeatable) |
| `--json` | Output JSON report to stdout |
| `--report-file <path>` | Write JSON report to file |
| `--quiet` | Suppress human-readable output |
| `--help` | Show usage information |

### Exit codes

| Code | Meaning | Pipeline behavior |
|------|---------|-------------------|
| `0` | All checks passed | Continue normally |
| `1` | Warnings only | Continue with degraded capability |
| `2` | Fatal failures | Cancel task immediately |

## Output formats

### Human-readable

```
Environment Check

Global checks
  ✓ git 2.43.0
  ✓ jq 1.7.1
  ✓ postgresql accepting connections
  ✓ redis PONG
  ✓ opencode_providers 7 configured (>= 2)

App: core (php >= 8.5)
  ✓ php_version 8.5.1 (>= 8.5)
  ✓ composer 2.8.1
  ✓ php_ext_json loaded
  ✓ php_ext_mbstring loaded

─────────────────────────────────────────
All 12 checks passed.
Report: .opencode/pipeline/env-report.json
```

### JSON report

```json
{
  "timestamp": "2026-03-20T12:00:00Z",
  "exit_code": 0,
  "summary": "All 12 checks passed",
  "duration_ms": 1200,
  "checks": [
    {
      "name": "postgresql",
      "category": "service",
      "status": "pass",
      "detail": "PostgreSQL accepting connections",
      "required_by": ["global"]
    }
  ],
  "environment": {
    "php": "8.5.1",
    "python": "3.12.4",
    "node": "22.3.0",
    "composer": "2.8.1",
    "npm": "10.8.0",
    "opencode_providers": "7",
    "postgresql": "16.2",
    "redis": "7.2.5"
  }
}
```

## Requirement registry

`builder/env-requirements.json` declares per-app prerequisites:

```json
{
  "global": {
    "tools": ["git", "jq"],
    "services": ["postgresql", "redis"]
  },
  "apps": {
    "core": {
      "runtime": "php",
      "min_version": "8.5",
      "tools": ["composer"],
      "extensions": ["json", "mbstring", "xml", "pdo_pgsql", "intl", "curl"],
      "deps_check": "composer check-platform-reqs"
    },
    "news-maker-agent": {
      "runtime": "python",
      "min_version": "3.12",
      "tools": ["pip"],
      "deps_check": "pip check"
    }
  }
}
```

### Extending for new apps

To add a new app:

1. Add entry to `builder/env-requirements.json`
2. Specify `runtime`, `min_version`, `tools`, and `extensions` as needed
3. Optionally add `deps_check` command for dependency validation

## Integration points

### Pipeline integration

`builder/pipeline.sh` runs env-check automatically after preflight:

```bash
preflight()      # Existing: checks opencode, docker, git
env_check()      # NEW: checks runtimes, services, per-app deps
setup_branch()   # Creates pipeline branch
run_agents()     # Runs agent sequence
```

### Handoff enrichment

On success, env-check writes to `.opencode/pipeline/handoff.md`:

```markdown
## Environment

**Runtime Versions**: PHP 8.5, Python 3.12, Node 22
**Services**: PostgreSQL 16, Redis 7
**Check Status**: pass — All 12 checks passed
```

### Monitor display

The pipeline monitor reads `.opencode/pipeline/env-report.json` and displays compact status:

```
Env: PHP 8.5 | Python 3.12 | Node 22 | PG | Redis | 12/12 checks
```

## Error handling

### Fatal failures (exit 2)

When prerequisites are missing:
1. Pipeline emits `ENV_FATAL` event
2. Task moves to `failed/` with env metadata
3. Telegram notification sent (if configured)
4. Pipeline exits with code 3

### Warnings (exit 1)

When non-critical tools are missing:
1. Pipeline emits `ENV_WARN` event
2. Warnings written to handoff
3. Pipeline continues with degraded capability

### Skipping env-check

Use `--skip-env-check` flag to bypass:

```bash
./builder/pipeline.sh --skip-env-check "Task description"
```

## Troubleshooting

### "jq not found"

Install jq:
```bash
apt install jq    # Ubuntu/Debian
brew install jq   # macOS
```

### "PostgreSQL not accepting connections"

Check if PostgreSQL is running:
```bash
docker ps | grep postgres
# or
pg_isready
```

### "Redis not responding"

```bash
docker ps | grep redis
# or
redis-cli ping
```

### "PHP extension not loaded"

Enable the extension in `php.ini`:
```bash
php -m | grep mbstring   # Check if loaded
docker-php-ext-enable mbstring  # In container
```

## Future extensibility

The architecture supports future `--auto-fix` mode that will invoke the `devcontainer-provisioner` skill to resolve fixable issues.
