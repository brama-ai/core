## ADDED Requirements

### Requirement: Telegram Event Publishing
The platform SHALL bridge normalized Telegram events to the Event Bus, dispatching them to all enabled agents that subscribe to the corresponding event type.

#### Scenario: Message dispatched to subscribed agents
- **WHEN** a `message_created` event is normalized from a Telegram update
- **THEN** `TelegramEventPublisher` calls `EventBus::dispatch("message_created", payload)` with the full normalized event envelope
- **AND** every enabled agent whose manifest declares `"message_created"` in its `events` array receives an A2A call with the event payload

#### Scenario: Event skipped for unsubscribed agents
- **WHEN** a `member_joined` event is dispatched
- **THEN** agents that do not list `"member_joined"` in their manifest `events` array do NOT receive an A2A call

#### Scenario: Disabled agent does not receive events
- **WHEN** an agent is disabled in the registry
- **THEN** it is excluded from event dispatch regardless of its event subscriptions

---

### Requirement: Event Bus A2A Dispatch
The `EventBus::dispatch()` method SHALL make actual A2A HTTP calls to subscribed agents' `a2a_endpoint` URLs via `A2AClient`, replacing the current placeholder implementation.

#### Scenario: Successful agent invocation
- **WHEN** the Event Bus dispatches an event to an agent with a valid `a2a_endpoint`
- **THEN** an HTTP POST is made to the agent's A2A endpoint with the event payload, trace headers (`traceparent`, `x-request-id`, `x-agent-run-id`), and the response is logged

#### Scenario: Agent invocation failure
- **WHEN** an A2A call to an agent fails (timeout, HTTP error, connection refused)
- **THEN** the error is logged with the agent name, event type, and error details
- **AND** dispatch continues to the remaining subscribed agents (fail-open per agent)

#### Scenario: Dispatch trace context
- **WHEN** the Event Bus dispatches an event
- **THEN** a `trace_id` and `request_id` are generated for the dispatch chain and included in all A2A calls for that event

---

### Requirement: Event Payload Contract
All events dispatched through the Event Bus SHALL use a standardized envelope format containing: `event_type`, `platform`, `bot_id`, `chat` (with `id`, `title`, `type`, `thread_id`), `sender` (with `id`, `username`, `first_name`, `role`, `is_bot`), `message` (with `id`, `text`, `reply_to_message_id`, `has_media`, `media_type`, `timestamp`), `trace_id`, and `request_id`.

#### Scenario: Agent receives full context
- **WHEN** an agent's A2A endpoint is called with a `message_created` event
- **THEN** the payload includes sender identity, chat context (including thread_id for forum topics), message content, and trace identifiers sufficient for the agent to process and respond

#### Scenario: Platform-agnostic payload
- **WHEN** an agent receives an event payload
- **THEN** the payload format is identical regardless of whether the source is Telegram or any future platform
- **AND** the `platform` field indicates the source for agents that need platform-specific behavior

---

### Requirement: Async Event Dispatch
The platform SHALL support asynchronous event dispatch for high-volume events by enqueuing them to a message transport (`agent_invoke` queue) rather than processing them synchronously in the webhook request.

#### Scenario: Async dispatch for message events
- **WHEN** a `message_created` event is published in a high-traffic group
- **THEN** the event is enqueued to the `agent_invoke` transport for processing by queue workers
- **AND** the webhook response is not blocked by agent processing time
