# Event log + observability system

## Причина скасування

Ця функціональність вже значною мірою реалізована в поточній кодовій базі:

- **A2A audit log** — таблиця `a2a_message_audit` записує всі виклики між агентами (skill, agent, trace_id, request_id, duration_ms, status, http_status_code, error_code)
- **Agent registry audit** — таблиця `agent_registry_audit` записує всі зміни в реєстрі агентів
- **Langfuse integration** — `LangfuseIngestionClient` записує traces і spans для всіх A2A calls та LLM invocations
- **W3C Trace Context** — `TraceContext::buildTraceparent()` генерує traceparent headers для distributed tracing
- **Structured logging** — `TraceEvent::build()` створює структуровані log events з event name, step, source, status
- **Payload sanitization** — `PayloadSanitizer` автоматично редактує sensitive дані перед логуванням
- **LiteLLM tags** — кожен LLM call отримує теги agent/method для фільтрації в Langfuse

Залишається потенційно корисним:
- DAU/WAU метрики (але це більше product analytics, не observability)
- Dashboard з агрегованими метриками

Рекомендація: якщо потрібен dashboard — створити окрему задачу на admin metrics page.
