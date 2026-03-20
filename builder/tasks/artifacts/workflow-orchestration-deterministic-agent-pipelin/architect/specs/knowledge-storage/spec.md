## ADDED Requirements

### Requirement: OpenSearch Knowledge Index
The system SHALL maintain an OpenSearch index `knowledge_entries_v1` with a defined mapping for storing structured knowledge documents.

#### Scenario: Document stored with full schema
- **WHEN** the worker successfully extracts a knowledge entry
- **THEN** the document is indexed in `knowledge_entries_v1` with fields: `title`, `body`, `tags`, `category`, `tree_path`, `embedding`, `source_message_id`, `message_link`, `created_at`, `created_by`

#### Scenario: Index missing on startup
- **WHEN** the knowledge-agent service starts and `knowledge_entries_v1` does not exist
- **THEN** the service creates the index with the canonical mapping before accepting any requests

---

### Requirement: Message Link Metadata
Every knowledge entry MUST store a direct link back to the source message in the originating chat, preserving traceability to the original conversation context.

#### Scenario: Telegram message link stored
- **WHEN** a knowledge entry is extracted from a Telegram message with a known `chat_id` and `message_id`
- **THEN** the entry stores `message_link` as a Telegram deep link (`https://t.me/c/{chat_id}/{message_id}`)

#### Scenario: Message link unavailable
- **WHEN** source message metadata does not contain sufficient identifiers to construct a link
- **THEN** `message_link` is stored as `null` and the entry is still indexed

---

### Requirement: Knowledge Tree Path
Each knowledge entry SHALL carry a `tree_path` field representing its position in the hierarchical knowledge tree, derived from category and tags during extraction.

#### Scenario: Tree path assigned on extraction
- **WHEN** the `EnrichMetadata` workflow node processes an extracted entry
- **THEN** `tree_path` is set to a slash-separated hierarchy string (e.g., `Technology/PHP/Symfony`)

#### Scenario: Top-level entry without subcategory
- **WHEN** an entry has only a top-level category and no subcategory tag
- **THEN** `tree_path` equals the category name alone (e.g., `Technology`)

---

### Requirement: Embedding Storage
Each knowledge entry SHALL store a dense vector embedding of its content for semantic search.

#### Scenario: Embedding generated and stored
- **WHEN** a knowledge entry is created via extraction or manual admin CRUD
- **THEN** the system generates an embedding from `title + body` using the configured embedding model and stores it in the `embedding` field

#### Scenario: Embedding model change
- **WHEN** the configured embedding model changes
- **THEN** a re-index job MUST be triggered; the old index version is kept until re-indexing completes
