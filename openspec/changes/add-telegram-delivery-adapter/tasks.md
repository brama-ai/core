# Tasks: add-telegram-delivery-adapter

## 1. Prerequisites Check

- [ ] 1.1 Verify `ChannelAdapterInterface` exists at `src/src/Delivery/Adapter/ChannelAdapterInterface.php` with `send(DeliveryPayload, array): DeliveryResult` and `supports(string): bool` methods — if not, this task is blocked by `add-delivery-channels` implementation
- [ ] 1.2 Verify `DeliveryPayload`, `DeliveryTarget`, and `DeliveryResult` VOs exist in `src/src/Delivery/` — if not, blocked by `add-delivery-channels`

## 2. Adapter Implementation

- [ ] 2.1 Create `src/src/Telegram/Delivery/TelegramDeliveryAdapter.php` implementing `ChannelAdapterInterface`:
  - Constructor: `TelegramSender $sender`, `TelegramBotRegistry $botRegistry`, `LoggerInterface $logger`
  - `supports(string $type): bool` — returns `true` when `$type === 'telegram'`
  - `send(DeliveryPayload $payload, array $channelConfig): DeliveryResult` — orchestrates address parsing, content mapping, and TelegramSender call
- [ ] 2.2 Implement address parsing: split `$channelConfig['metadata']['target_address']` or use `DeliveryTarget.address` (depending on how `DeliveryService` passes it) by colon — `chat_id` before colon, `thread_id` after colon (optional)
- [ ] 2.3 Implement bot resolution: read `$channelConfig['metadata']['bot_id']`, validate bot exists via `TelegramBotRegistry::getBot()`, return failed `DeliveryResult` if bot not found
- [ ] 2.4 Implement content type mapping: `markdown` to `MarkdownV2`, `text` to `null` (no parse mode), `card` to `HTML`, unknown to `null`
- [ ] 2.5 Call `TelegramSender::send($botId, $chatId, $payload->body, $options)` where `$options` includes `thread_id` (if parsed) and `parse_mode` (if mapped)
- [ ] 2.6 Map Telegram API response to `DeliveryResult`: if `$result['ok'] === true`, return `status: delivered` with `externalMessageId` from `$result['result']['message_id']`; otherwise return `status: failed` with `errorMessage` from `$result['description']`
- [ ] 2.7 Wrap the send call in try/catch for network exceptions — return `DeliveryResult` with `status: failed` and exception message

## 3. Service Registration

- [ ] 3.1 Register `App\Telegram\Delivery\TelegramDeliveryAdapter` in `src/config/services.yaml` with tag `delivery.adapter` and attribute `type: telegram`
- [ ] 3.2 Verify autowiring resolves `TelegramSender`, `TelegramBotRegistry`, and `LoggerInterface` — no explicit argument binding needed

## 4. Unit Tests

- [ ] 4.1 Create `src/tests/Unit/Telegram/Delivery/TelegramDeliveryAdapterTest.php` using Codeception unit suite
- [ ] 4.2 Test `supports()`: returns `true` for `'telegram'`, `false` for `'slack'`, `'webhook'`, `'openclaw'`
- [ ] 4.3 Test successful delivery: mock `TelegramSender::send()` returning `['ok' => true, 'result' => ['message_id' => 12345]]` — assert `DeliveryResult.status === 'delivered'` and `externalMessageId === '12345'`
- [ ] 4.4 Test address parsing with thread: address `'-1001234567890:42'` — assert `TelegramSender::send()` called with `chatId = '-1001234567890'` and `options['thread_id'] = '42'`
- [ ] 4.5 Test address parsing without thread: address `'-1001234567890'` — assert `TelegramSender::send()` called with `chatId = '-1001234567890'` and no `thread_id` in options
- [ ] 4.6 Test content type mapping: `markdown` payload → `options['parse_mode'] = 'MarkdownV2'`; `text` → no `parse_mode`; `card` → `options['parse_mode'] = 'HTML'`
- [ ] 4.7 Test bot not found: mock `TelegramBotRegistry::getBot()` returning `null` — assert `DeliveryResult.status === 'failed'` with appropriate error message
- [ ] 4.8 Test Telegram API failure: mock `TelegramSender::send()` returning `['ok' => false, 'description' => 'Forbidden: bot was blocked by the user']` — assert `DeliveryResult.status === 'failed'` with error message
- [ ] 4.9 Test network exception: mock `TelegramSender::send()` throwing `\RuntimeException` — assert `DeliveryResult.status === 'failed'`
- [ ] 4.10 Test missing bot_id in channel config: `$channelConfig['metadata']` without `bot_id` key — assert `DeliveryResult.status === 'failed'`

## 5. Documentation

- [ ] 5.1 Update `docs/delivery-channels.md` (if it exists) — add Telegram adapter section: channel type `telegram`, required metadata fields (`bot_id`), address format (`chat_id:thread_id`), content type mapping
- [ ] 5.2 Add inline PHPDoc on `TelegramDeliveryAdapter` class and methods documenting the address format and content type mapping

## 6. Quality Checks

- [ ] 6.1 Run `phpstan analyse` — zero errors at level 8
- [ ] 6.2 Run `php-cs-fixer check` — no style violations
- [ ] 6.3 Run `codecept run unit` — all unit tests pass including new `TelegramDeliveryAdapterTest`
