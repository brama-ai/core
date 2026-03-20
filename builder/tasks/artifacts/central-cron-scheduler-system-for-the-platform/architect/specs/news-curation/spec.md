## ADDED Requirements

### Requirement: First-Stage Ranking Agent
The system SHALL run a first AI agent that evaluates newly crawled raw-news items and ranks them by relevance and interest.

#### Scenario: Ranker scores eligible items
- **WHEN** a crawl run completes with new raw items
- **THEN** the first AI agent assigns a score or relevance outcome to each eligible item

#### Scenario: Low-signal item is rejected
- **WHEN** the ranker determines an item is spam, duplicate, or not relevant to AI news
- **THEN** the system marks the item as `discarded` or non-selected and excludes it from the rewrite stage

### Requirement: Top-Ten Selection
The system SHALL select no more than 10 raw-news items for editorial rewriting in a single processing run.

#### Scenario: More than ten good items available
- **WHEN** more than 10 items pass the relevance threshold
- **THEN** the system promotes only the top 10 highest-ranked items to the second stage

#### Scenario: Fewer than ten good items available
- **WHEN** fewer than 10 items pass the relevance threshold
- **THEN** the system promotes only the qualifying items without creating filler entries

### Requirement: Second-Stage Rewrite Agent
The system SHALL run a second AI agent that translates and rewrites selected items into a publication-ready format.

#### Scenario: Selected item becomes publication-ready
- **WHEN** the second AI agent produces valid structured output for a selected raw item
- **THEN** the system creates or updates a curated record with `status = ready`

#### Scenario: Rewrite output violates constraints
- **WHEN** the second AI agent returns malformed content or content that violates guardrails
- **THEN** the system rejects the output and does not mark the item as `ready`

### Requirement: Source Attribution
The system SHALL preserve references to the original source for every publication-ready news item.

#### Scenario: Curated item includes canonical reference
- **WHEN** a curated item is created from a raw item
- **THEN** the curated record stores the original source URL and source identity for link-out to the full article

#### Scenario: Missing source link blocks readiness
- **WHEN** the rewrite stage cannot attach a valid source reference
- **THEN** the system leaves the item in a non-ready state for correction or rejection
