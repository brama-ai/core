# Examples

## PHP — Using LlmRequestContext (Core)

```php
use App\LLM\LiteLlmClient;
use App\LLM\LlmRequestContext;

$context = new LlmRequestContext(
    agentName: 'core',
    featureName: 'core.agent_chat',
    requestId: 'chat_' . bin2hex(random_bytes(8)),
    traceId: $traceId,
    sessionId: $conversationId, // groups all turns in one Langfuse session
);

$response = $liteLlmClient->chatCompletion(
    messages: $messages,
    tools: $tools,
    context: $context,
);
```

The `LlmRequestContext` produces the full Langfuse-compatible metadata:

```php
$context->metadata();
// Returns:
// [
//     'trace_id' => '...',
//     'trace_name' => 'core.core.agent_chat',
//     'session_id' => $conversationId,
//     'generation_name' => 'core.agent_chat',
//     'tags' => ['agent:core', 'method:core.agent_chat'],
//     'trace_user_id' => 'service=core;feature=core.agent_chat;request_id=chat_...',
//     'trace_metadata' => [
//         'request_id' => 'chat_...',
//         'agent_name' => 'core',
//         'feature_name' => 'core.agent_chat',
//     ],
// ]
```

## PHP — Inline Metadata (Hello Agent)

For agents that build HTTP requests manually:

```php
$userTag = sprintf('service=%s;feature=%s;request_id=%s', $agent, $feature, $requestId);

$body = json_encode([
    'model' => $model,
    'messages' => $messages,
    'user' => $userTag,
    'metadata' => [
        'trace_id' => $traceId,
        'trace_name' => $agent . '.' . $feature,
        'session_id' => $requestId,
        'generation_name' => $feature,
        'tags' => ['agent:' . $agent, 'method:' . $feature],
        'trace_user_id' => $userTag,
        'trace_metadata' => [
            'request_id' => $requestId,
            'agent_name' => $agent,
            'feature_name' => $feature,
        ],
    ],
    'tags' => ['agent:' . $agent, 'method:' . $feature],
]);
```

## Python — News Maker Agent

```python
SERVICE_NAME = "news-maker-agent"
FEATURE_NAME = "news.ranker.run_ranking"

def _trace_context():
    request_id = request_id_var.get("") or f"llm-ranker-{uuid.uuid4()}"
    trace_id = trace_id_var.get("")

    user_tag = f"service={SERVICE_NAME};feature={FEATURE_NAME};request_id={request_id}"
    metadata = {
        "trace_id": trace_id,
        "trace_name": f"{SERVICE_NAME}.{FEATURE_NAME}",
        "session_id": request_id,
        "generation_name": FEATURE_NAME,
        "tags": [f"agent:{SERVICE_NAME}", f"method:{FEATURE_NAME}"],
        "trace_user_id": user_tag,
        "trace_metadata": {
            "request_id": request_id,
            "agent_name": SERVICE_NAME,
            "feature_name": FEATURE_NAME,
        },
    }
    return request_id, trace_id, headers, user_tag, metadata

# Usage:
request_id, trace_id, headers, user_tag, metadata = _trace_context()
response = client.chat.completions.create(
    model=model,
    messages=[...],
    user=user_tag,
    metadata=metadata,
    extra_headers=headers,
    extra_body={"tags": [f"agent:{SERVICE_NAME}", f"method:{FEATURE_NAME}"]},
)
```

## curl — Manual Test

```bash
curl -sS http://localhost:4000/v1/chat/completions \
  -H 'Authorization: Bearer dev-key' \
  -H 'Content-Type: application/json' \
  -d '{
    "model": "minimax/minimax-m2.5",
    "messages": [{"role":"user","content":"Hello"}],
    "metadata": {
      "trace_id": "a1b2c3d4e5f67890a1b2c3d4e5f67890",
      "trace_name": "manual-test.greeting",
      "session_id": "test-session-001",
      "generation_name": "greeting",
      "tags": ["agent:manual", "method:greeting"],
      "trace_user_id": "tester",
      "trace_metadata": {"request_id": "req-001"}
    },
    "tags": ["agent:manual", "method:greeting"]
  }'
```

## End-to-End Agent Chain Example

A user sends a message via OpenClaw. The platform orchestrates:

```
1. OpenClaw receives message
   → session_id = "chat_user123_thread42"

2. Core resolves tool, calls hello-agent via A2A
   → trace_id = "abcdef1234567890abcdef1234567890"
   → LangfuseIngestionClient records A2A span

3. Hello-agent calls LLM via LiteLLM
   → metadata.trace_id = "abcdef1234567890abcdef1234567890"  (same trace)
   → metadata.session_id = "req_hello_abc"
   → metadata.generation_name = "a2a.hello.greet"
   → LiteLLM callback → Langfuse generation event

4. In Langfuse UI:
   Trace "abcdef..." contains:
   ├── Span: "core.a2a.call" (from LangfuseIngestionClient)
   └── Generation: "a2a.hello.greet" (from LiteLLM callback)
```

Both the orchestration span and LLM generation appear under the same trace, linked by `trace_id`.
