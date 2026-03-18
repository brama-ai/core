## ADDED Requirements

### Requirement: Grounded Wiki Chat
The system SHALL provide a public chat interface embedded in the wiki that answers only from published wiki pages.

#### Scenario: Chat answers from wiki pages
- **WHEN** a user submits a question with relevant published pages available
- **THEN** the system returns an answer grounded in those pages
- **AND** includes citations or links to the source pages

#### Scenario: Chat declines when wiki lacks evidence
- **WHEN** the system cannot retrieve relevant published pages
- **THEN** the chat response states that the wiki does not contain enough information

### Requirement: Published Pages Power Retrieval
Only published wiki pages SHALL be used for retrieval and grounding.

#### Scenario: Draft page excluded from chat grounding
- **WHEN** a draft page exists with relevant text
- **THEN** it is not used in retrieval for public chat
