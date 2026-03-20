<!-- batch: 20260312_160247 | status: pass | duration: 1604s | branch: pipeline/implement-change-add-delivery-channels -->
<!-- priority: 2 -->
# Implement change: add-delivery-channels

Add channel-agnostic outbound message delivery to Core: database tables, service layer, channel adapters (Webhook, OpenClaw, Slack, Teams), admin UI, and security (idempotency, rate limiting, audit log).

## OpenSpec

- Proposal: openspec/changes/add-delivery-channels/proposal.md
- Design: openspec/changes/add-delivery-channels/design.md
- Tasks: openspec/changes/add-delivery-channels/tasks.md
- Spec delta: openspec/changes/add-delivery-channels/specs/delivery-channels/spec.md

## Context

- This is the foundation for all outbound push messaging — must be implemented first
- Depends on nothing; `add-openclaw-push-endpoint` and `add-scheduler-delivery` depend on this
- Pattern follows existing DBAL repository + admin controller + Twig template architecture
- Adapter wiring uses Symfony tagged services with `!tagged_iterator`
- Admin UI follows existing scheduler admin patterns (table + modal + status badges)

## Key files to create/update

### In apps/core/:
- `migrations/Version20260313000001.php` (new — delivery_channels + delivery_log tables)
- `src/Delivery/DeliveryTarget.php` (new)
- `src/Delivery/DeliveryPayload.php` (new)
- `src/Delivery/DeliveryResult.php` (new)
- `src/Delivery/DeliveryServiceInterface.php` (new)
- `src/Delivery/DeliveryService.php` (new)
- `src/Delivery/DeliveryChannelRepositoryInterface.php` (new)
- `src/Delivery/DeliveryChannelRepository.php` (new)
- `src/Delivery/DeliveryLogRepositoryInterface.php` (new)
- `src/Delivery/DeliveryLogRepository.php` (new)
- `src/Delivery/Adapter/ChannelAdapterInterface.php` (new)
- `src/Delivery/Adapter/WebhookAdapter.php` (new)
- `src/Delivery/Adapter/OpenClawAdapter.php` (new)
- `src/Delivery/Adapter/SlackAdapter.php` (new)
- `src/Delivery/Adapter/TeamsAdapter.php` (new)
- `src/Controller/Admin/DeliveryChannelsController.php` (new)
- `src/Controller/Admin/DeliveryChannelLogsController.php` (new)
- `src/Controller/Api/Internal/DeliveryChannelTestController.php` (new)
- `templates/admin/delivery-channels/index.html.twig` (new)
- `templates/admin/delivery-channels/logs.html.twig` (new)
- `config/services.yaml` (modified — adapter wiring)
- `tests/Unit/Delivery/DeliveryServiceTest.php` (new)
- `tests/Unit/Delivery/WebhookAdapterTest.php` (new)
- `tests/Unit/Delivery/SlackAdapterTest.php` (new)
- `tests/Unit/Delivery/TeamsAdapterTest.php` (new)
- `tests/Functional/Delivery/DeliveryChannelRepositoryTest.php` (new)
- `tests/Functional/Delivery/DeliveryLogRepositoryTest.php` (new)

### In docs/:
- `docs/delivery-channels.md` (new)

## Validation

- openspec validate add-delivery-channels --strict
- `make analyse` — 0 errors
- `make cs-check` — 0 violations
- `make test` — all tests pass
