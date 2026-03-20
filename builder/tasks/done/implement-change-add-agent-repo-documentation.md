<!-- batch: 20260312_172036 | status: pass | duration: 943s | branch: pipeline/implement-change-add-agent-repo-documentation -->
<!-- priority: 3 -->
# Implement change: add-agent-repo-documentation

Add standalone documentation, compose.fragment.yaml, and .env.local.example to external agent
repositories (hello-agent, wiki-agent). Update platform onboarding docs to reflect GHCR
image-first workflow.

## OpenSpec

- Proposal: openspec/changes/add-agent-repo-documentation/proposal.md
- Tasks: openspec/changes/add-agent-repo-documentation/tasks.md
- Spec delta: openspec/changes/add-agent-repo-documentation/specs/external-agent-onboarding/spec.md

## Context

- Agent repos already pushed to GitHub with Dockerfile and GHCR CI:
  - https://github.com/nmdimas/a2a-hello-agent
  - https://github.com/nmdimas/a2a-wiki-agent
- Platform compose files already updated to use `image: ghcr.io/nmdimas/a2a-*-agent:main`
  with `build:` fallback (see compose.agent-hello.yaml, compose.agent-wiki.yaml)
- Existing platform docs: docs/guides/external-agents/en/
- Existing agent contract: docs/agent-requirements/conventions.md
- hello-agent: PHP 8.5 + Symfony 7, Apache
- wiki-agent: Node.js 20, TypeScript, Express, PostgreSQL, OpenSearch
- **Previous attempt failed at validator**: PHPStan hit 128M memory limit on hello-agent.
  Do NOT modify `apps/hello-agent/phpstan.neon` — use `--memory-limit=512M` CLI flag instead.
  Do NOT create `docker/openclaw/.env` — it's not needed for this task.
- **Do NOT attempt to push to external repos** (`/private/tmp/a2a-*`) — sandbox blocks it.
  Just create the files in `apps/hello-agent/` and `apps/wiki-agent/`; external sync is manual.

## Key files to create/update

### In apps/hello-agent/:
- README.md (new)
- compose.fragment.yaml (new)
- .env.local.example (new)

### In apps/wiki-agent/:
- README.md (new)
- compose.fragment.yaml (new)
- .env.local.example (new)

### Platform docs:
- docs/guides/external-agents/en/onboarding.md (update — add GHCR section)
- docs/guides/external-agents/en/repository-structure.md (update — add CI + GHCR)
- docs/guides/external-agents/ua/ mirrors (update)

## Validation

- make hello-cs-check
- make hello-analyse (use --memory-limit=512M if needed)
- openspec validate add-agent-repo-documentation --strict
- Verify compose.fragment.yaml has required labels and healthcheck
