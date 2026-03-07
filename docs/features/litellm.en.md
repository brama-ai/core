# LiteLLM

Documentation for the local LiteLLM gateway in AI Community Platform.

## Purpose

`LiteLLM` is the platform-owned LLM proxy in local development:

- provides a single model access point for agents;
- isolates provider credentials from agent code;
- standardizes access through OpenAI-compatible APIs (`/v1/...`).

## Access

- URL: `http://localhost:4000`
- Admin shortcut: `Admin -> Інструменти -> LiteLLM`
- API auth: `Authorization: Bearer dev-key` (local default)
- UI login: `http://localhost:4000/ui/login` (requires DB connection)
- UI credentials (local): `admin` / `dev-key`

## Credentials

### 1) Access to LiteLLM API

- Gateway API key: `dev-key`
- Source: `compose.yaml` -> `litellm.environment.LITELLM_MASTER_KEY`

This key is used by local agents to call LiteLLM.

### 2) LiteLLM access to OpenRouter

- Provider key: `OPENROUTER_API_KEY`
- Source: `.env.local`
- Injection into container: `compose.yaml` -> `litellm.env_file` + `litellm.environment`

Without a valid `OPENROUTER_API_KEY`, LiteLLM cannot execute completion/embedding requests against OpenRouter.
The key is loaded from `.env.local` via `compose.yaml -> litellm.env_file`.

## LLM Trace Context & Langfuse

LiteLLM proxy is configured to send LLM generation events to Langfuse via `success_callback` / `failure_callback`. Agents pass Langfuse-compatible metadata fields in every LLM request.

Full documentation: [`docs/features/litellm-requests/`](litellm-requests/overview.md)

### Required metadata fields

| Field | Description |
|-------|-------------|
| `trace_id` | One orchestration run ID |
| `trace_name` | `<agent>.<feature>` |
| `session_id` | Chat/thread ID |
| `generation_name` | Name of the specific LLM call step |
| `tags` | `["agent:<name>", "method:<feature>"]` |
| `trace_user_id` | `service=...;feature=...;request_id=...` |
| `trace_metadata` | `{request_id, agent_name, feature_name}` |

### Implementation per agent

| Agent | Language | Implementation |
|-------|----------|---------------|
| `core` | PHP | `LlmRequestContext` DTO → `LiteLlmClient::chatCompletion()` |
| `hello-agent` | PHP | Inline metadata in `callLlm()` |
| `knowledge-agent` | PHP | `TracingHttpClient` decorator (automatic) |
| `news-maker-agent` | Python | `_trace_context()` helper → OpenAI client |

### Langfuse UI

- URL: `http://localhost:8086`
- Login: `admin@local.dev` / `test-password`
- Debug: see [langfuse-integration.md](litellm-requests/langfuse-integration.md)

This is applied in `core`, `hello-agent`, `knowledge-agent`, and `news-maker-agent`.

### 3) LiteLLM DB (for `/ui/login`)

- `DATABASE_URL=postgresql://app:app@postgres:5432/litellm`
- Configured in `compose.yaml` + `docker/litellm/config.yaml`
- The `litellm` database is created on fresh setups via `docker/postgres/init/02_create_litellm_db.sql`

For already existing Postgres volumes, create the DB once manually:

```bash
make litellm-db-init
```

## Models (current local preset)

Config: `docker/litellm/config.yaml`

- `minimax/minimax-m2.5` -> `openrouter/minimax/minimax-m2.5`
- `gpt-4o-mini` -> alias to `openrouter/minimax/minimax-m2.5` (compatibility for existing agents)

By default, agents in this repository use `minimax/minimax-m2.5` through LiteLLM.

## Troubleshooting

### `Authentication Error, Not connected to DB!`

Symptom: the error appears on `http://localhost:4000/ui/login`.

Cause: LiteLLM cannot access Postgres metadata DB `litellm` (commonly with an older `postgres-data` volume).

Fix:

```bash
docker compose up -d postgres
make litellm-db-init
docker compose logs --tail=100 litellm
```

## Quick verification

### List models

```bash
docker compose exec litellm python - <<'PY'
import urllib.request
req = urllib.request.Request(
    'http://127.0.0.1:4000/v1/models',
    headers={'Authorization': 'Bearer dev-key'},
)
with urllib.request.urlopen(req, timeout=5) as r:
    print(r.status)
    print(r.read().decode('utf-8'))
PY
```

Expected: HTTP `200`, response includes `minimax/minimax-m2.5` and `gpt-4o-mini`.

### Completion check

```bash
curl -sS http://localhost:4000/v1/chat/completions \
  -H 'Authorization: Bearer dev-key' \
  -H 'Content-Type: application/json' \
  -d '{
    "model": "minimax/minimax-m2.5",
    "messages": [{"role":"user","content":"ping"}]
  }'
```

## Key rotation (local)

1. Update `LITELLM_MASTER_KEY` in `compose.yaml` (or move it to env).
2. Update `LITELLM_API_KEY` in services that call LiteLLM.
3. Restart:

```bash
docker compose up -d litellm core knowledge-agent knowledge-worker news-maker-agent
```
