# External Agent Repository Structure

This document describes the required and recommended layout for an agent repository that is
maintained outside the platform core repository.

## Required Files

```
my-agent/                       ← repository root
  Dockerfile                    ← required: builds the agent container image
  compose.fragment.yaml         ← required: compose service definition for platform integration
  .env.local.example            ← required: documents all required env vars (no secrets)
  README.md                     ← recommended: setup and usage instructions with a GHCR badge
  .github/workflows/            ← recommended: docker-publish.yml CI for building images
```

## compose.fragment.yaml

The compose fragment is the integration contract between the agent repository and the platform
workspace. It defines the service, labels, environment, and healthcheck.

**Minimal valid fragment:**

```yaml
services:
  my-agent:
    image: ghcr.io/<your-org>/my-agent:main
    build:
      context: ./projects/my-agent
      dockerfile: Dockerfile
    labels:
      - com.a2a.agent=true
      - com.a2a.agent.id=my-agent
      - com.a2a.agent.network=a2a-network
    env_file:
      - path: ./projects/my-agent/.env.local
        required: false
    healthcheck:
      test: ["CMD-SHELL", "curl -sf http://localhost/health || exit 1"]
      interval: 30s
      timeout: 10s
      retries: 3
      start_period: 30s
    networks:
      - a2a-network
```

**Rules:**

- `image` MUST point to a published GHCR image.
- `build.context` MUST point to `./projects/<agent-name>/` as a fallback.
- Service name MUST end with `-agent`.
- Label `com.a2a.agent=true` MUST be present.
- Network MUST be `a2a-network`.
- Healthcheck MUST be configured.

## .env.local.example

Documents all environment variables the agent requires. Operators copy this to
`projects/<agent-name>/.env.local` and fill in their values.

```bash
# =============================================================================
# my-agent — Local Development Secrets
# =============================================================================
# Copy to .env.local and fill in your values.
# =============================================================================

# Required: API key for the external service
MY_AGENT_API_KEY=

# Optional: override the default model
# MY_AGENT_MODEL=gpt-4o
```

## Required HTTP Endpoints

Every agent MUST implement these endpoints regardless of source origin:

| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `/health` | GET | None | Returns `{"status":"ok"}` with HTTP 200 |
| `/api/v1/manifest` | GET | None | Returns Agent Card JSON |
| `/api/v1/a2a` | POST | Internal token | A2A skill handler (if skills declared) |

See `docs/agent-requirements/conventions.md` for the full contract including manifest schema,
A2A request/response envelope, and convention verification rules.

## Migrations

If the agent uses a database, it MUST declare startup migrations in the manifest:

```json
{
  "storage": {
    "postgres": {
      "db_name": "my_agent",
      "user": "my_agent",
      "password": "my_agent",
      "startup_migration": {
        "enabled": true,
        "mode": "best_effort",
        "command": "php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration || true"
      }
    }
  }
}
```

The platform runs this command automatically during the **Install** lifecycle step.

## Versioning

- Use semantic versioning (`MAJOR.MINOR.PATCH`) in the manifest `version` field
- Tag releases in the agent repository
- Operators upgrade by pulling a new tag and running `make external-agent-up name=<agent-name>`

## Compatibility Matrix

The agent repository SHOULD document which platform versions it is compatible with. Example:

```markdown
## Platform Compatibility

| Agent version | Platform version |
|---------------|-----------------|
| 1.x           | >= 0.8.0        |
| 0.x           | 0.6.x – 0.7.x   |
```

## Pilot: hello-agent

The `hello-agent` is the designated pilot for the external repository pattern. It is the simplest
bundled agent (PHP/Symfony, no database, no workers) and serves as the reference implementation
for the external checkout workflow.

When `hello-agent` is extracted to its own repository, its `compose.fragment.yaml` will follow
the template above with `build.context: ./projects/hello-agent` and GHCR image.

---

## Best Practices

### Dockerfile
- Keep your image as small as possible. Use multi-stage builds.
- Ensure your entrypoints handle SIGTERM gracefully.

### CI: GitHub Actions
- Use a `.github/workflows/docker-publish.yml` workflow to automatically build and push your Docker image to GHCR upon pushing to `main`. This ensures the platform can use the latest version using `docker pull`.

### README.md
- Use a **GHCR packaging badge** at the top of your agent's `README.md` linked to your image packages page.
- Explicitly instruct users to pull the GHCR image for local run.
