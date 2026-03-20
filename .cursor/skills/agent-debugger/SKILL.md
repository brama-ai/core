---
name: agent-debugger
description: >
  Debug agent issues in the AI Community Platform. Use when the user reports
  a problem with an agent call, tool invocation, LLM response, or needs
  to trace a request end-to-end. Triggers on: "debug", "trace", "why did",
  "what happened", "not working", "wrong response", "check logs",
  "investigate", "diagnose".
---

# Agent Debugger

Investigate and diagnose issues with agent calls, tool invocations, and LLM
responses in the AI Community Platform.

## When to Use

- User reports an agent returned wrong/empty/garbled response
- User wants to trace a request end-to-end
- User has a trace_id or request_id and wants to see what happened
- User says "it's not working" or "the response is wrong"

## Debugging Flow

Follow these steps in order. Stop as soon as you identify the issue.

### Step 1 — Gather Context

Ask the user for (or extract from conversation):

1. **trace_id** — shown in CLI output or admin UI
2. **Which agent** — hello-agent, knowledge-agent, news-maker-agent
3. **What was expected** vs what actually happened
4. **When** — approximate time (to narrow log search)

### Step 2 — Check OpenSearch Logs

Platform logs go to OpenSearch in daily indices: `platform_logs_YYYY_MM_DD`.

**Admin UI**: `/admin/logs/trace/{trace_id}` — shows full call chain.

**Direct query via CLI** (if admin UI not available):

```bash
# Search by trace_id
docker compose exec core php -r "
\$ch = curl_init('http://opensearch:9200/platform_logs_*/_search');
curl_setopt_array(\$ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode([
        'query' => ['term' => ['trace_id' => 'TRACE_ID_HERE']],
        'sort' => [['sequence_order' => 'asc']],
        'size' => 50,
    ]),
]);
\$r = json_decode(curl_exec(\$ch), true);
foreach (\$r['hits']['hits'] ?? [] as \$h) {
    \$s = \$h['_source'];
    printf(\"%s | %-8s | %-35s | %s | %s\n\",
        \$s['@timestamp'] ?? '',
        \$s['source_app'] ?? '',
        \$s['event_name'] ?? '',
        \$s['status'] ?? '',
        \$s['message'] ?? '',
    );
}
"
```

**Key fields to look at:**
- `event_name` — identifies the step (e.g., `core.a2a.outbound.started`)
- `status` — `started`, `completed`, `failed`
- `error_code` — reason for failure
- `step_input` / `step_output` — sanitized payload (may be truncated)
- `duration_ms` — how long each step took
- `source_app` / `target_app` — which service logged it

**Typical event sequence for a successful tool call:**
1. `core.invoke.tool_resolved` — skill mapped to agent
2. `core.a2a.outbound.started` — HTTP request to agent
3. `hello.intent.greet_me.started` — agent received request
4. `hello.llm.call.started` — agent calling LLM
5. `hello.llm.call.completed` — LLM responded
6. `hello.intent.greet_me.completed` — agent returning result
7. `core.a2a.outbound.completed` — core received response

### Step 3 — Check Agent Config

Verify the agent's system_prompt and config in the database:

```bash
docker compose exec core php -r "
\$pdo = new PDO('pgsql:host=postgres;dbname=ai_community_platform', 'app', 'app');
\$row = \$pdo->query(\"SELECT name, config, enabled FROM agent_registry WHERE name = 'AGENT_NAME'\")->fetch(PDO::FETCH_ASSOC);
echo json_encode(\$row, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
"
```

**Common issues:**
- `system_prompt` is empty or wrong (was overwritten by tests)
- `enabled` is false
- Config JSON is malformed

### Step 4 — Check LiteLLM

LiteLLM proxies all LLM calls. Check its logs:

```bash
# Recent LiteLLM Docker logs
docker compose logs litellm --since 10m --tail 50

# LiteLLM UI: http://localhost:4000/ui/
# Login: admin / dev-key (check .env for LITELLM_MASTER_KEY)
```

**What to look for:**
- HTTP 429 (rate limit) or 500 (provider error)
- Model routing errors
- Token/cost limits exceeded
- Empty responses from the model

### Step 5 — Check Langfuse

Langfuse captures LLM traces with full input/output.

**Langfuse UI**: `http://localhost:8086/`
- Login: credentials from `.env` (`LANGFUSE_PUBLIC_KEY` / `LANGFUSE_SECRET_KEY`)
- Filter by trace_id to see the exact prompt and response
- Check `Generation` tab for token counts, latency, and cost

**What to look for:**
- System prompt content — is it what you configured?
- User message content — is it what you expected?
- Output tokens — 0 means empty response
- Model used — is it the right one?

### Step 6 — Check A2A Audit Table

The `a2a_message_audit` table records every tool invocation:

```bash
docker compose exec core php -r "
\$pdo = new PDO('pgsql:host=postgres;dbname=ai_community_platform', 'app', 'app');
\$stmt = \$pdo->query(\"SELECT skill, agent, status, duration_ms, error_code, actor, created_at FROM a2a_message_audit ORDER BY created_at DESC LIMIT 10\");
foreach (\$stmt as \$row) {
    printf(\"%s | %-25s | %-15s | %s | %dms | %s | %s\n\",
        \$row['created_at'],
        \$row['skill'],
        \$row['agent'],
        \$row['status'],
        \$row['duration_ms'],
        \$row['error_code'] ?? '-',
        \$row['actor'],
    );
}
"
```

## Common Issues & Solutions

### Agent returns empty/garbled response
1. Check Langfuse — is the model returning empty output?
2. Check LiteLLM — is the model rate-limited or erroring?
3. Try a different model in LiteLLM config
4. Check `max_tokens` in the agent handler (default 200 may be too low)

### system_prompt not applied
1. Check `agent_registry.config` in DB — is `system_prompt` set?
2. Check if functional/e2e tests overwrote it (`.env.test` should use `_test` DB)
3. Check A2AClient — `system_prompt` is read from `config` and sent in payload

### Tool call succeeds but Core LLM ignores the result
1. Core LLM system prompt says "present results directly without rephrasing"
2. If the model still paraphrases, check the model — smaller models ignore instructions
3. The `resultJson` passed to the LLM includes full tool output

### "Malformed UTF-8" errors
1. `postJson` in A2AClient has `JSON_INVALID_UTF8_SUBSTITUTE` flag
2. If the error persists, check terminal encoding: `locale` should show UTF-8
3. Docker container locale: check `LANG` env var

### actor shows "openclaw" for CLI calls
1. Verify `AgentChatCommand` passes `$actor` to `a2aClient->invoke()`
2. Check that `.env.test` points to `ai_community_platform_test` DB

## Observability Architecture

```
User (CLI / Telegram)
  |
  v
Core (PHP/Symfony)
  |-- Logs --> OpenSearch (platform_logs_YYYY_MM_DD)
  |-- A2A --> Agent (hello/knowledge/news-maker)
  |              |-- Logs --> OpenSearch
  |              |-- LLM --> LiteLLM proxy
  |                            |-- DB --> litellm (PostgreSQL)
  |                            |-- Callback --> Langfuse
  |-- LLM --> LiteLLM proxy (for core.agent_chat)
  |-- Audit --> a2a_message_audit (PostgreSQL)
  |-- Traces --> Langfuse (direct POST)
```

## Key URLs

| Service | URL | Credentials |
|---------|-----|-------------|
| Admin Logs | `/admin/logs` | Admin login |
| Trace View | `/admin/logs/trace/{trace_id}` | Admin login |
| Langfuse | `http://localhost:8086/` | See `.env` |
| LiteLLM UI | `http://localhost:4000/ui/` | See `LITELLM_MASTER_KEY` |
| OpenSearch | `http://localhost:9200/` | No auth (dev) |
