## ADDED Requirements

### Requirement: Public Published News List
The system SHALL provide a public-facing web page that lists curated news items that have already been published.

#### Scenario: Published items visible publicly
- **WHEN** a visitor opens the news page
- **THEN** the system displays only items where `status = published`

#### Scenario: Unpublished items hidden
- **WHEN** a curated item is still `draft` or `ready`
- **THEN** it is not visible on the public news page

### Requirement: Published News Metadata
The system SHALL display source-aware metadata for each published news item.

#### Scenario: Source link shown
- **WHEN** a published item is rendered in the public UI
- **THEN** the UI includes a link to the original full article

#### Scenario: Publication timestamp shown
- **WHEN** a published item is rendered in the public UI
- **THEN** the UI includes the publication date/time recorded by the system

### Requirement: Historical Browsing
The system SHALL support browsing older published news items without exposing unpublished states.

#### Scenario: Older items loaded
- **WHEN** the visitor requests additional pages or older entries
- **THEN** the system returns older published items in reverse chronological order

#### Scenario: No older items remain
- **WHEN** the visitor reaches the end of the published history
- **THEN** the UI indicates there are no more published news entries to load
