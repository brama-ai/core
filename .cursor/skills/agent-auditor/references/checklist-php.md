# PHP/Symfony Agent Checklist

Checks for agents built with PHP 8.5 + Symfony 7.

## S: Structure & Build

| ID | Check | How to Verify | PASS | WARN | FAIL |
|----|-------|---------------|------|------|------|
| S-01 | `src/` directory exists | Glob `apps/<agent>/src/` | Exists | — | Missing |
| S-02 | `config/` directory exists | Glob `apps/<agent>/config/` | Exists | — | Missing |
| S-03 | `tests/` directory exists | Glob `apps/<agent>/tests/` | Exists | — | Missing |
| S-04 | `Kernel.php` exists | Glob `apps/<agent>/src/Kernel.php` | Exists | — | Missing |
| S-05 | Dockerfile exists | Glob `docker/<agent>/Dockerfile` | Exists | — | Missing |
| S-06 | Dockerfile uses php:8.5 base | Read Dockerfile, check FROM line | Matches `php:8.5` | Different PHP version | No Dockerfile |
| S-07 | Compose service defined | Grep `compose.yaml` for service name | Found | — | Missing |
| S-08 | `composer.json` exists | Glob `apps/<agent>/composer.json` | Exists | — | Missing |
| S-09 | `composer.lock` exists | Glob `apps/<agent>/composer.lock` | Exists | — | Missing (deps not locked) |
| S-10 | `bin/console` exists | Glob `apps/<agent>/bin/console` | Exists | — | Missing |
| S-11 | `public/` directory exists | Glob `apps/<agent>/public/` | Exists | — | Missing |

## T: Testing

| ID | Check | How to Verify | PASS | WARN | FAIL |
|----|-------|---------------|------|------|------|
| T-01 | `codeception.yml` exists | Glob `apps/<agent>/codeception.yml` | Exists | — | Missing |
| T-02 | Unit tests exist | Glob `apps/<agent>/tests/Unit/**/*Test.php` | >= 1 file | — | 0 files |
| T-03 | Functional tests exist | Glob `apps/<agent>/tests/Functional/**/*Cest.php` | >= 1 file | — | 0 files |
| T-04 | `phpstan.neon` exists | Glob `apps/<agent>/phpstan.neon` | Exists | — | Missing |
| T-05 | PHPStan level is 8 | Read phpstan.neon, check `level:` value | Level 8 | Level < 8 | Missing or 0 |
| T-06 | `.php-cs-fixer.php` exists | Glob `apps/<agent>/.php-cs-fixer.php` | Exists | — | Missing |
| T-07 | Test-to-src ratio adequate | Count .php in src/ vs test files | Ratio > 0.3 | 0.1 – 0.3 | < 0.1 |
| T-08 | Makefile has test target | Grep Makefile for `<agent>-test` or `test:` | Found | — | Missing |
| T-09 | Makefile has analyse target | Grep Makefile for `<agent>-analyse` or `analyse:` | Found | — | Missing |
| T-10 | Makefile has cs-check target | Grep Makefile for `<agent>-cs-check` or `cs-check:` | Found | — | Missing |

## C: Configuration & Agent Card (agents only — skip for core)

| ID | Check | How to Verify | PASS | WARN | FAIL |
|----|-------|---------------|------|------|------|
| C-01 | Manifest controller exists | Grep src/ for `'/api/v1/manifest'` | Found | — | Missing |
| C-02 | Agent Card has required fields | Read ManifestController, check for `name`, `version` | Both present | — | Missing required |
| C-03 | Agent Card has `url` field | Check manifest response for `url` (valid URL) | Present | Only deprecated `a2a_endpoint` | Neither |
| C-04 | Agent Card has structured skills | Check `skills` array contains AgentSkill objects `{id, name, description}` | Structured objects | Legacy string array | No skills |
| C-05 | Agent Card has `capabilities` | Check for `capabilities` object `{streaming, pushNotifications}` | Present | — | Missing |
| C-06 | Agent Card has `provider` | Check for `provider` object `{organization, url}` | Present | — | Missing |
| C-07 | Agent Card has I/O modes | Check for `defaultInputModes`, `defaultOutputModes` arrays | Both present | One only | Neither |
| C-08 | Compose label `ai.platform.agent=true` | Grep compose.yaml | Found | — | Missing |
| C-09 | `config/reference.php` exists | Glob | Exists | — | Missing |
| C-10 | Environment variables documented | `.env` or `.env.dev` exists with content | Exists | Empty | Missing |
| C-11 | `services.yaml` exists | Glob `apps/<agent>/config/services.yaml` | Exists | — | Missing |
| C-12 | Scheduled jobs reference valid skills | If manifest has `scheduled_jobs`, each entry's `skill_id` must exist in the `skills` array | All skill_ids match | — | Unknown skill_id found |
| C-13 | Scheduled jobs have valid cron | If manifest has `scheduled_jobs`, each entry with `cron_expression` must be a valid 5-field cron (regex `^\S+ \S+ \S+ \S+ \S+$`) | All valid | — | Invalid cron expression |
| C-14 | Scheduled jobs have unique names | If manifest has `scheduled_jobs`, all `name` values must be unique within the agent | All unique | — | Duplicate names |

## X: Security

| ID | Check | How to Verify | PASS | WARN | FAIL |
|----|-------|---------------|------|------|------|
| X-01 | `security.yaml` exists | Glob `apps/<agent>/config/packages/security.yaml` | Exists | — | Missing |
| X-02 | No hardcoded secrets in src/ | Grep src/ for `password=`, `secret=`, `api_key=`, `token=` (literals, not env refs) | None found | — | Found |
| X-03 | `.env` has no real secrets | Read `.env`, check for placeholder/dev values | Dev values only | — | Real secrets |
| X-04 | `.gitignore` covers sensitive files | Check for `.env.local`, `var/`, `vendor/` | All covered | Partial | Missing .gitignore |
| X-05 | CSRF protection configured | Grep security.yaml for `enable_csrf` | Enabled | — | Disabled / absent |
| X-06 | Auth firewall configured | Grep security.yaml for firewall with `security: true` | Configured | `security: false` | No security.yaml |

## O: Observability

| ID | Check | How to Verify | PASS | WARN | FAIL |
|----|-------|---------------|------|------|------|
| O-01 | Observability module exists | Glob `apps/<agent>/src/Observability/` | Exists with files | — | Missing |
| O-02 | TraceContext class exists | Glob for `TraceContext.php` | Exists | — | Missing |
| O-03 | Langfuse integration present | Grep src/ for `Langfuse` or `langfuse` | Found | — | Not found |
| O-04 | Compose env has LANGFUSE vars | Grep compose.yaml for LANGFUSE_ env vars | Present | — | Missing |
| O-05 | Structured logging used | Grep src/ for `LoggerInterface` | Found | — | Not found |
| O-06 | Health endpoint exists | Grep src/ for `'/health'` route | Found | — | Missing |
| O-07 | Log messages include `trace_id` | Grep src/ for `trace_id` in logger calls; every controller/handler that receives trace_id must pass it to logger context | All pass trace_id | Some missing | No trace_id usage |
| O-08 | Log messages include `request_id` | Grep src/ for `request_id` in logger calls; every controller/handler that receives request_id must pass it to logger context | All pass request_id | Some missing | No request_id usage |
| O-09 | A2A handler logs have correlation IDs | Read A2A handler(s), verify all log calls include trace_id and request_id in context array | All have IDs | Partial | None |
| O-10 | Error/warning paths include context | Grep src/ for `->warning(` and `->error(` calls; check they include relevant identifiers (agent, tool, trace_id) not just a bare string | All have context | Some bare | Majority bare |
| O-11 | LLM calls log duration and model | If agent calls external LLM, grep for `duration_ms` and `model` near LLM call code | Both logged | One of two | Neither |
| O-12 | Monolog config with OpenSearch handler | Glob `apps/<agent>/config/packages/monolog.yaml`, check for opensearch handler | Configured | — | Missing |
| O-13 | LLM calls include `tags` field | If agent calls LLM, grep src/ for `'tags'` near LLM call code; must contain `agent:<name>` and `method:<feature>` (both top-level `tags` and inside `metadata.tags`) | Both tags present | Partial | No tags |
| O-14 | LLM metadata is Langfuse-compatible | If agent calls LLM, grep src/ for `'metadata'` near LLM call code; must contain `trace_id`, `trace_name`, `session_id`, `generation_name`, `tags`, `trace_user_id`, `trace_metadata` (see `docs/features/litellm-requests/tracing-contract.md`) | All required fields | Has trace_id but missing others | No metadata |

## D: Documentation

| ID | Check | How to Verify | PASS | WARN | FAIL |
|----|-------|---------------|------|------|------|
| D-01 | Agent PRD exists | Glob `docs/agents/en/*<agent-slug>*.md` | Exists | — | Missing |
| D-02 | PRD in both languages | Check `docs/agents/ua/` and `docs/agents/en/` | Both | One only | Neither |
| D-03 | Listed in index.md | Grep `index.md` for agent reference | Found | — | Missing |
| D-04 | OpenAPI / API documentation exists | Glob for openapi spec or Grep for API doc references | Found | — | Missing |

## M: Database & Migrations

| ID | Check | How to Verify | PASS | WARN | FAIL |
|----|-------|---------------|------|------|------|
| M-01 | Migrations directory exists | Glob `apps/<agent>/migrations/` | Exists | — | Missing (if agent has DB) |
| M-02 | Migrations have files | Count files in migrations/ | >= 1 | 0 but dir exists | No dir (if has DB) |
| M-03 | Naming follows convention | Filenames match `Version*.php` | All match | — | Non-conforming |
| M-04 | `doctrine_migrations.yaml` exists | Glob config/packages/ | Exists | — | Missing (if has DB) |
| M-05 | Makefile has migrate target | Grep Makefile for `<agent>-migrate` or `migrate:` | Found | — | Missing (if has DB) |
| M-06 | Compose env has DATABASE_URL | Grep compose.yaml for DATABASE_URL | Present | — | Missing (if has DB) |

## Q: Standards Compliance

| ID | Check | How to Verify | PASS | WARN | FAIL |
|----|-------|---------------|------|------|------|
| Q-01 | `declare(strict_types=1)` everywhere | Grep src/ for files missing it | All have it | — | Some missing |
| Q-02 | Namespace follows `App\` | Grep src/ for namespace declarations | All `App\` | — | Non-standard |
| Q-03 | Controllers use route attributes | Grep for `#[Route(` in Controller files | Found | — | Missing |
| Q-04 | `.editorconfig` exists | Glob root or app dir | Exists | — | Missing |
| Q-05 | No direct agent-to-agent calls | Grep `src/` for hardcoded URLs to other agent service names (e.g. `http://knowledge-agent`, `http://news-maker-agent`). All inter-agent communication must go via `PLATFORM_CORE_URL` through the A2A gateway. | No matches | — | Direct agent URL found |
| Q-06 | Agent reads Accept-Language header | If agent has A2A endpoint, grep for `Accept-Language` in request header handling code (Symfony: `$request->headers->get('Accept-Language')` or similar). | Handler present | — | Not found (WARN if no i18n needed) |
