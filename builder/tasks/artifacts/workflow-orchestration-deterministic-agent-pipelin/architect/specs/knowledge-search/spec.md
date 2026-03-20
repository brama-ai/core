## ADDED Requirements

### Requirement: Hybrid Search
The system SHALL provide a hybrid search endpoint combining BM25 keyword matching and kNN vector similarity, returning ranked results with message-link metadata.

#### Scenario: Hybrid search returns relevant results
- **WHEN** a client sends GET `/api/v1/knowledge/search?q=symfony+middleware&mode=hybrid`
- **THEN** the system returns up to 10 entries ranked by combined score (BM25 weight 0.4, kNN weight 0.6)

#### Scenario: Keyword-only fallback
- **WHEN** `mode=keyword` is specified
- **THEN** the system executes a BM25-only `multi_match` query on `title` and `body`

#### Scenario: Vector-only search
- **WHEN** `mode=vector` is specified
- **THEN** the system executes a kNN query on the `embedding` field using a query embedding generated from the search text

#### Scenario: Empty query handled
- **WHEN** `q` is empty or whitespace
- **THEN** the system returns `422 Unprocessable Entity`

---

### Requirement: Search Result Metadata
Every search result MUST include the `message_link` field so that users can navigate to the original chat message.

#### Scenario: Message link included in results
- **WHEN** a search returns matching entries
- **THEN** each result includes: `id`, `title`, `body` (truncated to 300 chars), `tags`, `category`, `tree_path`, `message_link`, `score`

#### Scenario: Null message link displayed gracefully
- **WHEN** a result has `message_link: null`
- **THEN** the web UI and API response omit the link field rather than showing a broken URL

---

### Requirement: Ukrainian Language Search Support
The OpenSearch index MUST use a Ukrainian language analyzer for `title` and `body` fields to support morphological matching.

#### Scenario: Morphological match
- **WHEN** a user searches for a Ukrainian word in its base form
- **THEN** the system returns entries containing inflected forms of that word (e.g., searching "місто" matches entries containing "міста", "місті")

#### Scenario: Analyzer applied at index creation
- **WHEN** the `knowledge_entries_v1` index is created
- **THEN** `title` and `body` mappings specify `"analyzer": "ukrainian"` (or a custom analyzer with Ukrainian stop words and stemmer)
