# Change: Add Hello-World Agent

## Why
The platform needs a minimal reference agent that demonstrates the full agent lifecycle: client-facing webview, admin-configurable description and system prompt, and compliance with all platform agent conventions. It serves as a scaffold for future agents and a living integration test.

## What Changes
- New Symfony 7 agent app in `apps/hello-agent/`
- Client webview endpoint rendering a configurable greeting (default: "Hello, World!")
- `GET /health` and `GET /api/v1/manifest` endpoints per agent conventions
- Docker, Traefik, and Compose setup on port `:8085`
- Admin panel: editable "description" and "system_prompt" fields stored in `agent_registry.config`
- Core admin UI extended with config edit form for any agent
- Codeception unit + functional tests for the agent
- Convention tests pass (`make conventions-test`)
- Makefile targets for the new agent

## Impact
- Affected specs: `hello-world-agent` (new capability)
- Affected code: `apps/hello-agent/`, `docker/hello-agent/`, `compose.yaml`, `docker/traefik/traefik.yml`, `Makefile`, `apps/core/templates/admin/agents.html.twig`, `apps/core/src/Controller/Admin/AgentsController.php`
