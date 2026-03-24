## 1. Database & Migration

- [ ] 1.1 Create migration `Version20260313000001.php` in `apps/brama-core/migrations/`:
  - Table `delivery_channels`: `id` (UUID PK), `name` (VARCHAR 128 NOT NULL UNIQUE), `type` (VARCHAR 32 NOT NULL), `endpoint` (TEXT NOT NULL), `auth_scheme` (VARCHAR 32 DEFAULT 'bearer'), `auth_token` (TEXT nullable), `capabilities` (JSONB DEFAULT '["text"]'), `rate_limit_per_minute` (INTEGER DEFAULT 60), `enabled` (BOOLEAN DEFAULT TRUE), `metadata` (JSONB DEFAULT '{}'), `created_at` (TIMESTAMPTZ DEFAULT now()), `updated_at` (TIMESTAMPTZ DEFAULT now())
  - Table `delivery_log`: `id` (UUID PK), `channel_id` (UUID FK → delivery_channels), `idempotency_key` (VARCHAR 256 NOT NULL), `status` (VARCHAR 32 NOT NULL), `content_type` (VARCHAR 32), `content_preview` (TEXT), `target_address` (VARCHAR 256), `external_message_id` (VARCHAR 256), `error_message` (TEXT), `trace_id` (VARCHAR 64), `request_id` (VARCHAR 64), `duration_ms` (INTEGER), `created_at` (TIMESTAMPTZ DEFAULT now())
  - UNIQUE index on `delivery_log(idempotency_key)`
  - Index on `delivery_log(channel_id, created_at DESC)`
  - Index on `delivery_channels(type, enabled)`

## 2. Value Objects

- [ ] 2.1 Create `apps/brama-core/src/Delivery/DeliveryTarget.php` — immutable VO: `channelId`, `address` (chat_id/channel/phone/URL), `metadata` array
- [ ] 2.2 Create `apps/brama-core/src/Delivery/DeliveryPayload.php` — immutable VO: `contentType` (text/markdown/card), `body`, `metadata`, `idempotencyKey`, `traceId`, `requestId`
- [ ] 2.3 Create `apps/brama-core/src/Delivery/DeliveryResult.php` — immutable VO: `status` (delivered/failed/rate_limited/duplicate), `externalMessageId`, `errorMessage`, `durationMs`

## 3. Repositories

- [ ] 3.1 Create `apps/brama-core/src/Delivery/DeliveryChannelRepository.php` + interface — DBAL-based:
  - `findAll(): array`, `findById(string $id): ?array`, `findByType(string $type): array`
  - `create(array $data): string` (returns UUID)
  - `update(string $id, array $data): void`
  - `delete(string $id): void`
  - `toggleEnabled(string $id, bool $enabled): void`
- [ ] 3.2 Create `apps/brama-core/src/Delivery/DeliveryLogRepository.php` + interface — DBAL-based:
  - `log(string $channelId, string $idempotencyKey, string $status, array $data): string`
  - `existsByIdempotencyKey(string $key): bool`
  - `findByChannel(string $channelId, int $limit, int $offset): array`
  - `countByChannel(string $channelId): int`
  - `countRecentByChannel(string $channelId, int $windowSeconds): int` (for rate limiting)

## 4. Channel Adapters

- [ ] 4.1 Create `apps/brama-core/src/Delivery/Adapter/ChannelAdapterInterface.php`:
  - `send(DeliveryPayload $payload, array $channelConfig): DeliveryResult`
  - `supports(string $type): bool`
- [ ] 4.2 Create `apps/brama-core/src/Delivery/Adapter/WebhookAdapter.php`:
  - POST JSON to endpoint
  - Bearer auth: `Authorization: Bearer <token>`
  - HMAC auth: HMAC-SHA256 body signature in `X-Signature-256`
  - Include `X-Idempotency-Key` header
  - 10s timeout
- [ ] 4.3 Create `apps/brama-core/src/Delivery/Adapter/OpenClawAdapter.php`:
  - POST platform push envelope to OpenClaw push endpoint
  - Payload: `{ chat_id, content: { type, body }, idempotency_key, trace_id }`
  - Bearer auth with channel-specific token
- [ ] 4.4 Create `apps/brama-core/src/Delivery/Adapter/SlackAdapter.php`:
  - POST Slack Block Kit JSON to webhook URL
  - Convert markdown to Slack mrkdwn
  - No auth header (token embedded in webhook URL)
- [ ] 4.5 Create `apps/brama-core/src/Delivery/Adapter/TeamsAdapter.php`:
  - POST Adaptive Card JSON to webhook URL
  - Render markdown in card body TextBlock
  - No auth header (token embedded in webhook URL)

## 5. Delivery Service

- [ ] 5.1 Create `apps/brama-core/src/Delivery/DeliveryService.php` + interface:
  - `deliver(DeliveryTarget $target, DeliveryPayload $payload): DeliveryResult`
  - Steps: resolve channel → check enabled → check idempotency → check rate limit → select adapter → send → log result
  - Constructor injection: `DeliveryChannelRepository`, `DeliveryLogRepository`, iterable of `ChannelAdapterInterface`
- [ ] 5.2 Wire adapters as tagged services in `config/services.yaml` with `!tagged_iterator`

## 6. Admin UI

- [ ] 6.1 Create `apps/brama-core/src/Controller/Admin/DeliveryChannelsController.php`:
  - `GET /admin/delivery-channels` — list all channels with stats (total deliveries, last 24h, failures)
  - `POST /admin/delivery-channels` — create channel from form
  - `POST /admin/delivery-channels/{id}/toggle` — enable/disable
  - `DELETE /admin/delivery-channels/{id}` — remove channel
- [ ] 6.2 Create `apps/brama-core/templates/admin/delivery-channels/index.html.twig` — channel list with create modal, test button, toggle, stats
- [ ] 6.3 Create `apps/brama-core/src/Controller/Admin/DeliveryChannelLogsController.php`:
  - `GET /admin/delivery-channels/{id}/logs` — paginated log viewer
- [ ] 6.4 Create `apps/brama-core/templates/admin/delivery-channels/logs.html.twig` — log table with status badges, timing, content preview
- [ ] 6.5 Create `apps/brama-core/src/Controller/Api/Internal/DeliveryChannelTestController.php`:
  - `POST /api/v1/internal/delivery-channels/{id}/test` — send test message, return JSON result
- [ ] 6.6 Add "Канали доставки" link to admin sidebar navigation

## 7. Tests

- [ ] 7.1 Unit test: `DeliveryServiceTest` — idempotency check (duplicate returns `duplicate`), rate limit exceeded (returns `rate_limited`), adapter selection by type, disabled channel rejection
- [ ] 7.2 Unit test: `WebhookAdapterTest` — Bearer auth headers, HMAC signature correctness, timeout handling
- [ ] 7.3 Unit test: `SlackAdapterTest` — Block Kit JSON format, mrkdwn conversion
- [ ] 7.4 Unit test: `TeamsAdapterTest` — Adaptive Card format
- [ ] 7.5 Functional test: `DeliveryChannelRepositoryTest` — CRUD operations, toggle enabled
- [ ] 7.6 Functional test: `DeliveryLogRepositoryTest` — log creation, idempotency key uniqueness, rate limit count query

## 8. Documentation

- [ ] 8.1 Create `docs/delivery-channels.md` — developer-facing: architecture, adapter interface, channel types, payload format, idempotency, rate limiting
- [ ] 8.2 Update `docs/agent-requirements/conventions.md` — add delivery channel reference for agents that produce push content

## 9. Quality Checks

- [ ] 9.1 Run `phpstan analyse` — 0 errors at level 8
- [ ] 9.2 Run `php-cs-fixer check` — no style violations
- [ ] 9.3 Run `codecept run` — all tests pass
