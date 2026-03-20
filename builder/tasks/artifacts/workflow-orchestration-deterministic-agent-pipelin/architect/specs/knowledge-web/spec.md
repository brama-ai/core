## ADDED Requirements

### Requirement: Ukrainian Knowledge Encyclopedia
The system SHALL provide a publicly accessible web page at `/wiki` displaying the knowledge base in Ukrainian, with a two-panel layout: knowledge tree on the left, entry content in the center.

#### Scenario: Encyclopedia loads with tree and entry
- **WHEN** a user navigates to `/wiki`
- **THEN** the left panel shows the knowledge tree, the center panel shows a welcome message in Ukrainian

#### Scenario: User selects tree node
- **WHEN** a user clicks a category or subcategory node in the left tree
- **THEN** the center panel shows a list of entry titles in that category

#### Scenario: User opens knowledge entry
- **WHEN** a user clicks an entry title
- **THEN** the center panel renders the full entry body (Markdown) with title, tags, category, and a link to the source message (if available)

#### Scenario: Encyclopedia disabled by admin
- **WHEN** an admin disables the web encyclopedia in settings
- **THEN** `/wiki` returns `503 Service Unavailable` with a Ukrainian-language maintenance message

---

### Requirement: Web Encyclopedia Search
The web encyclopedia SHALL include a search bar that calls the hybrid search API and displays results inline.

#### Scenario: User searches from encyclopedia
- **WHEN** a user types a query in the search bar and presses Enter or clicks the search button
- **THEN** the center panel is replaced with a list of search results, each showing title, body excerpt, tags, and source link

#### Scenario: No results found
- **WHEN** the search returns no entries
- **THEN** the center panel shows a Ukrainian-language "нічого не знайдено" message with a suggestion to refine the query

---

### Requirement: Source Message Link Display
The web encyclopedia MUST display a clickable link to the original Telegram message on every knowledge entry page where the link is available.

#### Scenario: Source link shown
- **WHEN** an entry has a non-null `message_link`
- **THEN** the entry page displays a "Перейти до джерела" link opening the Telegram message in a new tab

#### Scenario: Source link absent
- **WHEN** `message_link` is null
- **THEN** the link is not rendered; no broken or empty anchor is shown
