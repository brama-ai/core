# Langfuse Integration

## How It Works

LiteLLM proxy is configured with Langfuse as a success/failure callback. When any agent sends an LLM request through the proxy, LiteLLM:

1. Forwards the request to the LLM provider (OpenRouter)
2. On completion (success or failure), extracts metadata from the request body
3. Maps metadata fields to Langfuse concepts
4. Sends a generation event to Langfuse

## LiteLLM Proxy Configuration

### Config file (`docker/litellm/config.yaml`)

```yaml
litellm_settings:
  success_callback: ["langfuse"]
  failure_callback: ["langfuse"]
```

### Environment variables (`compose.yaml` → litellm service)

```yaml
LANGFUSE_PUBLIC_KEY: lf_pk_local_dev
LANGFUSE_SECRET_KEY: lf_sk_local_dev
LANGFUSE_HOST: http://langfuse-web:3000
```

The host uses the internal Docker network address, not the external Traefik-routed URL.

## Langfuse Stack

- **Web UI**: `http://localhost:8086` (via Traefik)
- **Internal**: `http://langfuse-web:3000` (Docker network)
- **Compose file**: `compose.langfuse.yaml`
- **Login**: `admin@local.dev` / `test-password`
- **API keys**: `lf_pk_local_dev` / `lf_sk_local_dev`

## Two Integration Paths

### Path 1: LiteLLM Callbacks (LLM Generations)

**Scope:** Every LLM completion/embedding call through the proxy.

**How:** Automatic — agents set `metadata` in request body, LiteLLM extracts and forwards to Langfuse.

**What appears in Langfuse:** Generation events with model, tokens, latency, input/output, linked to traces via `trace_id`.

### Path 2: Custom LangfuseIngestionClient (A2A Orchestration)

**Scope:** A2A call orchestration, OpenClaw tool invocations.

**How:** PHP services (`core`, `hello-agent`) POST directly to Langfuse `/api/public/ingestion`.

**What appears in Langfuse:** Trace and span events for orchestration flow.

**Files:**
- `apps/core/src/Observability/LangfuseIngestionClient.php`
- `apps/hello-agent/src/Observability/LangfuseIngestionClient.php`

### Correlation

Both paths share `trace_id`. An A2A orchestration trace and the LLM generation it triggered will appear under the same trace in Langfuse.

## Debugging Missing Traces

### Check LiteLLM logs

```bash
make logs-litellm
```

Look for:
- `Langfuse` mentions during startup (callback registration)
- Errors like `LangfuseConnectionError` or `LangfuseAuthError`

### Verify Langfuse is reachable from LiteLLM

```bash
docker compose exec litellm curl -s http://langfuse-web:3000/api/public/health
```

### Verify credentials

```bash
docker compose exec litellm env | grep LANGFUSE
```

Expected:
```
LANGFUSE_PUBLIC_KEY=lf_pk_local_dev
LANGFUSE_SECRET_KEY=lf_sk_local_dev
LANGFUSE_HOST=http://langfuse-web:3000
```

### Test with manual request

```bash
curl -sS http://localhost:4000/v1/chat/completions \
  -H 'Authorization: Bearer dev-key' \
  -H 'Content-Type: application/json' \
  -d '{
    "model": "minimax/minimax-m2.5",
    "messages": [{"role":"user","content":"ping"}],
    "metadata": {
      "trace_id": "00000000000000000000000000000001",
      "trace_name": "manual-test",
      "session_id": "test-session-1",
      "generation_name": "ping-test",
      "tags": ["agent:manual", "method:test"],
      "trace_user_id": "tester",
      "trace_metadata": {"request_id": "req-manual-1"}
    }
  }'
```

Then check `http://localhost:8086` — a trace with ID ending in `...0001` should appear.

### Common issues

| Symptom | Cause | Fix |
|---------|-------|-----|
| No traces in Langfuse | Callback not initialized | Check `config.yaml` has `success_callback: ["langfuse"]` |
| `LangfuseConnectionError` | Wrong host | Ensure `LANGFUSE_HOST=http://langfuse-web:3000` (not localhost) |
| `LangfuseAuthError` | Wrong keys | Verify keys match `compose.langfuse.yaml` init config |
| Traces appear but no generations | Metadata format wrong | Verify `metadata` dict uses correct field names |
| Langfuse UI not loading | Stack not started | Run with `compose.langfuse.yaml` included |
