## ADDED Requirements

### Requirement: Outbound Locale Header
The A2A gateway client SHALL include an `Accept-Language` header in every outbound A2A HTTP request. The value SHALL match the current user's locale from the request context, defaulting to `uk` when no request context is available. This header enables agents to tailor their responses to the user's preferred language.

#### Scenario: Outbound A2A request carries locale header
- **WHEN** the A2A client sends a message to an agent while the platform request locale is `en`
- **THEN** the HTTP request to the agent includes `Accept-Language: en`

#### Scenario: Background A2A request uses default locale
- **WHEN** the A2A client sends a message from a background context without an active HTTP request
- **THEN** the HTTP request to the agent includes `Accept-Language: uk`
