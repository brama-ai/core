# Change: Add delivery channel abstraction for outbound message push

## Why

The platform currently has no way to proactively push messages to end-users. All communication is reactive — a user sends a message via OpenClaw and gets a response. Scheduled jobs, alerts, and agent-initiated notifications have no delivery path. A channel-agnostic delivery abstraction lets Core push messages to any transport (Telegram via OpenClaw, Slack, Teams, WhatsApp, SMS, generic webhooks) without agents knowing which channel is in use.

## What Changes

- **New DB table** `delivery_channels` — registry of configured outbound channels with type, endpoint, auth credentials, and capabilities
- **New DB table** `delivery_log` — audit trail of every delivery attempt with status, timing, idempotency key, and error details
- **New Doctrine migration** `Version20260313000001.php` — creates both tables
- **New interface** `ChannelAdapterInterface` — contract for all channel adapters: `send(DeliveryPayload): DeliveryResult`
- **New value object** `DeliveryTarget` — `channel_id` + transport-specific address (chat_id, channel name, phone, URL) + metadata
- **New value object** `DeliveryPayload` — message content, content type (text/markdown/card), metadata, idempotency key, trace context
- **New value object** `DeliveryResult` — status (delivered/failed/rate_limited), external message ID, error details
- **New service** `DeliveryService` — resolves channel from registry, selects adapter, formats payload, dispatches, logs result, handles rate limiting and idempotency
- **New service** `DeliveryChannelRepository` — DBAL-based CRUD for `delivery_channels`
- **New service** `DeliveryLogRepository` — DBAL-based writes/reads for `delivery_log`
- **New adapter** `WebhookAdapter` — generic HTTP POST with configurable auth (Bearer, HMAC, none)
- **New adapter** `OpenClawAdapter` — pushes via OpenClaw Gateway push endpoint (depends on `add-openclaw-push-endpoint`)
- **New adapter** `SlackAdapter` — Slack Incoming Webhook format
- **New adapter** `TeamsAdapter` — Microsoft Teams Incoming Webhook (Adaptive Card format)
- **New admin page** `/admin/delivery-channels` — CRUD for channels with test-send button
- **New admin page** `/admin/delivery-channels/{id}/logs` — delivery log viewer per channel
- **New internal API** `POST /api/v1/internal/delivery-channels/{id}/test` — send test message
- **Modified admin navigation** — add "Канали доставки" link to sidebar
- **Security**: per-channel auth tokens (never reuse `OPENCLAW_GATEWAY_TOKEN`), `idempotency_key` on every delivery (dedup on retry), per-channel rate limiting, all deliveries audit-logged

## Impact

- Affected specs: new capability `delivery-channels`; modifies `admin-tools-navigation` (new sidebar link); modifies `observability-integration` (delivery trace events)
- Affected code:
  - `apps/brama-core/src/Delivery/` (new namespace — all services, adapters, VOs)
  - `apps/brama-core/src/Controller/Admin/DeliveryChannelsController.php` (new)
  - `apps/brama-core/src/Controller/Api/Internal/DeliveryChannelTestController.php` (new)
  - `apps/brama-core/migrations/Version20260313000001.php` (new)
  - `apps/brama-core/templates/admin/delivery-channels/` (new)
  - `apps/brama-core/config/services.yaml` (adapter wiring)
