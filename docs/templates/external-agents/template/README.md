# External Agent Repository Template

This directory contains the minimum files required for an external agent repository that
integrates with the AI Community Platform.

## Required Files

| File | Purpose |
|------|---------|
| `compose.fragment.yaml` | Compose service definition for the platform workspace |
| `Dockerfile` | Docker build context (self-contained) |
| `src/` | Application source code |
| `.env.example` | Required environment variables |
| `README.md` | Setup and runtime instructions |

## Minimum Runtime Contract

Your agent MUST implement:

| Endpoint | Method | Response |
|----------|--------|----------|
| `/health` | GET | `{"status": "ok"}` with HTTP 200 |
| `/api/v1/manifest` | GET | Agent Card JSON (see conventions) |
| `/api/v1/a2a` | POST | A2A skill handler (if skills declared) |

## Checklist Before Publishing

- [ ] `compose.fragment.yaml` uses a service name ending in `-agent`
- [ ] `compose.fragment.yaml` includes `ai.platform.agent=true` label
- [ ] `compose.fragment.yaml` attaches to the `dev-edge` network
- [ ] `Dockerfile` copies source from the repository root (not from `apps/`)
- [ ] `/health` returns `{"status": "ok"}` with HTTP 200
- [ ] `/api/v1/manifest` returns valid JSON with `name`, `version`, and `url`
- [ ] All required env vars are documented in `.env.example`
- [ ] `make conventions-test AGENT_URL=http://localhost:<port>` passes

## References

- [External Agent Workspace](../external-agent-workspace.md)
- [Operator Onboarding Guide](../operator-onboarding.md)
- [Agent Platform Conventions](../../../../agent-requirements/conventions.md)
