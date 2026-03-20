# Change: Bootstrap Platform Foundation

## Why

`apps/core` currently runs a bare PHP stub on PHP 8.3 with no framework, no dev tooling, and no
test infrastructure. The local Docker Compose topology with Traefik routing is already in place
(see `add-local-dev-compose-topology`), but the `core` container behind it is a placeholder.
This change replaces the stub with a real Symfony 7 application on PHP 8.5, wires in the full
dev tooling stack (Codeception, PHPStan, PHP CS Fixer), establishes a Postgres connection,
delivers the first verifiable HTTP endpoint served through Traefik, adds Playwright for E2E
coverage, and introduces a local `LiteLLM` proxy so LLM traffic can be debugged through one
gateway during development.

## What Changes

- Fix PHP version mismatch: update `Dockerfile` and `composer.json` from 8.3 → 8.5
- Initialize Symfony 7 skeleton in `apps/core/`
- Configure Codeception (unit + functional suites)
- Configure PHPStan at level 8
- Configure PHP CS Fixer
- Establish Postgres connection via Doctrine DBAL (no schema/migrations yet)
- Add a local `LiteLLM` proxy service for inspecting and standardizing LLM requests
- Add `GET /health` as the first real HTTP endpoint, routed through Traefik on `http://localhost/health`
- Add Playwright E2E infrastructure under `tests/e2e/`
- Add `make` targets: `test`, `analyse`, `cs-check`, `cs-fix`, `e2e`
- Update agent requirements so LLM-capable agents declare `LiteLLM` proxy usage, model aliases,
  and prompt-safety limits

## Impact

- Affected specs: `platform-foundation` (new), `health-endpoint` (new), `e2e-testing` (new)
- Affected code: `apps/core/`, `compose.yaml`, `docker/`, `tests/`, `Makefile`, local docs
- Depends on: `add-local-dev-compose-topology` (Traefik topology must already be in place)
- **BREAKING**: Replaces `apps/core/public/index.php` PHP stub with a Symfony app
