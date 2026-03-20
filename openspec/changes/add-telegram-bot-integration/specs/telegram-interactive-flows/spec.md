## ADDED Requirements

### Requirement: Callback Query Routing
The platform SHALL route `callback_query` events from inline keyboard buttons to registered handlers based on pattern matching on the `callback_data` field.

#### Scenario: Flow-trigger callback routed
- **WHEN** a user presses a button with `callback_data: "flow:create_ad:channel_-100123"`
- **THEN** `TelegramCallbackRouter` matches the `flow:*` pattern and delegates to the conversation flow initiator
- **AND** `answerCallbackQuery` is called to acknowledge the press

#### Scenario: Moderation callback routed
- **WHEN** a moderator presses a button with `callback_data: "moderate:approve:draft_abc123"`
- **THEN** `TelegramCallbackRouter` matches the `moderate:*` pattern and delegates to `TelegramModerationService`

#### Scenario: Unknown callback data
- **WHEN** a callback_query arrives with unrecognized `callback_data`
- **THEN** `answerCallbackQuery` is called with `text: "Дія недоступна"` and no further processing occurs

#### Scenario: Expired callback
- **WHEN** a callback references a draft or flow that no longer exists (expired, already processed)
- **THEN** `answerCallbackQuery` is called with `text: "Ця дія вже не актуальна"` and `show_alert: true`

---

### Requirement: Conversational DM Flows
The platform SHALL support multi-step conversational flows in private messages (DMs) between the bot and a user, managed by a state machine with persistent state.

#### Scenario: User initiates announcement flow via channel button
- **WHEN** a user presses "Розмістити оголошення" in a channel
- **THEN** the bot sends a DM to the user: "Надішліть текст оголошення"
- **AND** a conversation state is created in Redis with `flow_type: "create_ad"`, `state: "collecting_text"`, `target: channel_id`

#### Scenario: User provides text
- **WHEN** the user sends a text message while in `collecting_text` state
- **THEN** the text is stored in conversation state
- **AND** the state advances to `collecting_media`
- **AND** the bot replies: "Додайте фото до оголошення (або надішліть /skip)"

#### Scenario: User skips optional photo
- **WHEN** the user sends `/skip` while in `collecting_media` state
- **THEN** the state advances to `preview` without media
- **AND** the bot sends a preview of the draft with buttons: [Опублікувати] [Редагувати] [Скасувати]

#### Scenario: User adds photo
- **WHEN** the user sends a photo while in `collecting_media` state
- **THEN** the photo `file_id` and optional caption are stored in conversation state
- **AND** the state advances to `preview`

#### Scenario: User confirms publication
- **WHEN** the user presses [Опублікувати] in the preview
- **THEN** a `telegram_drafts` record is created with `status: "pending"`
- **AND** if moderation is enabled, the draft is sent to the moderation pipeline
- **AND** if moderation is disabled (admin posting), the draft is published directly

#### Scenario: User cancels flow
- **WHEN** the user sends `/cancel` during any conversation state
- **THEN** the conversation state is cleared from Redis
- **AND** the bot confirms: "Створення оголошення скасовано."

#### Scenario: Conversation timeout
- **WHEN** the conversation state TTL expires (default 30 min) without user interaction
- **THEN** the state is automatically removed from Redis
- **AND** on the user's next message, the bot replies: "Попередня сесія завершилась. Почніть заново."

#### Scenario: Multiple concurrent flows rejected
- **WHEN** a user attempts to start a 4th concurrent flow (limit: 3)
- **THEN** the bot replies: "У вас вже є 3 активних діалоги. Завершіть або скасуйте один з них."

---

### Requirement: Deep-Link Start Parameters
The platform SHALL support Telegram deep-link `start` parameters (`t.me/bot?start=payload`) as a fallback mechanism for initiating DM flows when the user hasn't previously started a chat with the bot.

#### Scenario: Deep-link flow initiation
- **WHEN** a user clicks a `t.me/bot?start=create_ad_channel_-100123` link
- **THEN** Telegram opens a DM with the bot and sends `/start create_ad_channel_-100123`
- **AND** `TelegramCommandRouter` parses the start payload, extracts `flow_type: "create_ad"` and `target: "channel_-100123"`
- **AND** `TelegramConversationManager` initializes the flow

#### Scenario: Invalid deep-link payload
- **WHEN** a user sends `/start` with an unrecognized or malformed payload
- **THEN** the bot replies with a generic welcome message and ignores the invalid payload

---

### Requirement: Moderation Pipeline
The platform SHALL support a moderation workflow where user-submitted content is reviewed by moderators before publication, using inline keyboard buttons for approve/reject/edit actions.

#### Scenario: Draft submitted for moderation
- **WHEN** a user completes a DM flow and confirms publication
- **AND** moderation is enabled for this flow type
- **THEN** `TelegramModerationService` sends the draft preview to the configured moderation chat
- **AND** the preview includes author info (@username), content, media thumbnails, and buttons: [✅ Опублікувати] [❌ Відхилити] [✏️ Повернути автору]
- **AND** the user receives: "Ваше оголошення відправлено на модерацію. Очікуйте."

#### Scenario: Moderator approves draft
- **WHEN** a moderator presses [✅ Опублікувати]
- **THEN** the draft is published to the target channel/chat/thread via `TelegramSender`
- **AND** the draft status is updated to `published` with `moderator_user_id` and `published_at`
- **AND** the moderation message buttons are replaced with "✅ Опубліковано @moderator"
- **AND** the author receives in DM: "Ваше оголошення опубліковано!"

#### Scenario: Moderator rejects draft
- **WHEN** a moderator presses [❌ Відхилити]
- **THEN** the bot prompts the moderator: "Вкажіть причину відмови (або /skip):"
- **AND** after receiving the reason, the draft status is updated to `rejected`
- **AND** the moderation message buttons are replaced with "❌ Відхилено @moderator"
- **AND** the author receives in DM: "Ваше оголошення відхилено. Причина: {reason}"

#### Scenario: Moderator returns draft for editing
- **WHEN** a moderator presses [✏️ Повернути автору]
- **THEN** the draft status is updated to `editing`
- **AND** the author receives in DM: "Модератор повернув оголошення на редагування. Надішліть оновлений текст:"
- **AND** the conversation flow resumes at the `collecting_text` state with existing content pre-loaded

#### Scenario: Draft auto-expired
- **WHEN** a draft in `pending` status has been unmoderated for longer than the configured TTL (default 48 hours)
- **THEN** the draft status is updated to `expired`
- **AND** the moderation message buttons are replaced with "⏰ Протерміновано"
- **AND** the author receives in DM: "Ваше оголошення не було розглянуто вчасно. Спробуйте надіслати ще раз."

---

### Requirement: Mini App Rich Forms (optional)
The platform SHALL support optional Telegram Mini App (WebApp) forms as an alternative to multi-step DM conversations for complex input scenarios.

#### Scenario: Mini App button opens form
- **WHEN** a user presses an inline button with `WebAppInfo: {url: "https://platform/telegram/mini-app/create-ad"}`
- **THEN** Telegram opens the Mini App HTML form inside the chat interface
- **AND** the user can fill in all fields (text, photo, category) in a single screen

#### Scenario: Mini App form submitted
- **WHEN** the user submits the Mini App form via `WebApp.sendData(jsonPayload)`
- **THEN** the bot receives a message with `web_app_data.data` containing the JSON payload
- **AND** the normalizer produces a `mini_app_submitted` event
- **AND** the submission enters the same moderation pipeline as DM flow drafts

#### Scenario: Mini App form cancelled
- **WHEN** the user closes the Mini App without submitting
- **THEN** no event is generated and no state is created
