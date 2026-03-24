## 1. Push Route Handler

- [ ] 1.1 Add push route registration in `docker/openclaw/plugins/platform-tools/index.js`:
  - Register HTTP route `POST /api/v1/push` via OpenClaw plugin API (or Express middleware if plugin supports it)
  - Parse JSON body, validate required fields (`chat_id`, `content.body`)
  - Return 400 on validation failure with structured error
- [ ] 1.2 Implement push authentication:
  - Read `OPENCLAW_PUSH_TOKEN` from env
  - Validate `Authorization: Bearer <token>` header on every push request
  - Return 401 on missing/invalid token
  - Ensure `OPENCLAW_GATEWAY_TOKEN` is NOT accepted on this endpoint

## 2. Message Delivery

- [ ] 2.1 Implement Telegram send logic:
  - Use OpenClaw's bot API (`api.sendMessage()` or equivalent) to send to `chat_id`
  - Support `content.type = "text"` — plain text send
  - Support `content.type = "markdown"` — send with `parse_mode: MarkdownV2`
  - Handle Telegram API errors (chat not found, bot blocked, rate limited) and return structured error
- [ ] 2.2 Return standardized response:
  - Success: `{ "status": "delivered", "message_id": "<telegram_msg_id>" }`
  - Failure: `{ "status": "failed", "error": "<error_description>" }`
  - Duplicate: `{ "status": "duplicate" }`

## 3. Idempotency

- [ ] 3.1 Implement LRU idempotency cache:
  - In-memory Map with max 10,000 entries and 1h TTL per key
  - On push: check if `idempotency_key` exists in cache
  - If exists: return `duplicate` without sending
  - If new: proceed with send, then cache the key on success
- [ ] 3.2 Handle edge case: no `idempotency_key` in request — proceed without dedup (warn in logs)

## 4. Observability

- [ ] 4.1 Log push events to OpenSearch using existing `osLog()` infrastructure:
  - `openclaw.push.received` — on every incoming push request
  - `openclaw.push.delivered` — on successful Telegram send
  - `openclaw.push.failed` — on Telegram API error or validation failure
  - `openclaw.push.duplicate` — on idempotency hit
  - `openclaw.push.unauthorized` — on auth failure
- [ ] 4.2 Include in all log entries: `chat_id`, `status`, `duration_ms`, `trace_id`, `request_id`, `idempotency_key`, `content.type`, `content.metadata`

## 5. Docker & Environment

- [ ] 5.1 Add `OPENCLAW_PUSH_TOKEN` to `docker/openclaw/.env` (generate unique token, different from gateway token)
- [ ] 5.2 Pass `OPENCLAW_PUSH_TOKEN` in `compose.openclaw.yaml` environment section
- [ ] 5.3 Pass `OPENCLAW_PUSH_TOKEN` in `compose.openclaw.multi-bot.yaml` environment section
- [ ] 5.4 Add `OPENCLAW_PUSH_TOKEN` to `apps/brama-core/.env` (Core needs it for `OpenClawAdapter` config)

## 6. Documentation

- [ ] 6.1 Update `docker/openclaw/README.md` — add push endpoint section: URL, auth, payload format, response codes
- [ ] 6.2 Add push endpoint to `docs/templates/openclaw/frontdesk/TOOLS.md` — document the push contract

## 7. Tests

- [ ] 7.1 Manual test: send push via curl to running OpenClaw instance — verify message appears in Telegram
- [ ] 7.2 E2E test: `tests/e2e/tests/openclaw/push_endpoint_test.js`:
  - Test 401 on missing token
  - Test 401 on wrong token (using gateway token)
  - Test 400 on missing chat_id
  - Test 400 on missing content.body
  - Test 200 on valid push (mock or real chat)
  - Test duplicate detection with same idempotency_key

## 8. Quality Checks

- [ ] 8.1 ESLint/JSHint pass on modified plugin code (if linter configured)
- [ ] 8.2 E2E tests pass
