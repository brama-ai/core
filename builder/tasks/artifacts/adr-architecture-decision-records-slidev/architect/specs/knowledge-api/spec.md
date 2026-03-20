## ADDED Requirements

### Requirement: OpenAPI Documentation
The knowledge API MUST be documented with a machine-readable OpenAPI 3.1 specification and the spec MUST be served at `/api/v1/knowledge/openapi.json`.

#### Scenario: OpenAPI spec served
- **WHEN** a client sends GET `/api/v1/knowledge/openapi.json`
- **THEN** the system returns a valid OpenAPI 3.1 document describing all knowledge endpoints

#### Scenario: All endpoints documented
- **WHEN** the OpenAPI spec is validated with a standard linter
- **THEN** every endpoint has a summary, parameter schemas, response schemas, and at least one example

---

### Requirement: Message Upload API
The system SHALL provide POST `/api/v1/knowledge/upload` for submitting a batch of messages for async knowledge extraction.

#### Scenario: Upload accepted and batch_id returned
- **WHEN** authorized client sends a valid message array
- **THEN** response is `202 Accepted` with `{ "batch_id": "uuid", "message_count": N, "chunks_enqueued": M }`

#### Scenario: Upload requires authentication
- **WHEN** an unauthenticated client sends a POST to `/api/v1/knowledge/upload`
- **THEN** the system returns `401 Unauthorized`

---

### Requirement: Knowledge CRUD API
The system SHALL provide admin-authenticated REST endpoints for creating, reading, updating, and deleting knowledge entries.

#### Scenario: Admin creates entry
- **WHEN** an admin-authenticated client sends POST `/api/v1/knowledge/entries` with valid entry payload
- **THEN** the system stores the entry in OpenSearch (generating embedding) and returns `201 Created` with the full entry

#### Scenario: Admin updates entry
- **WHEN** an admin sends PUT `/api/v1/knowledge/entries/{id}` with changed `body`
- **THEN** the system updates the entry and regenerates the embedding

#### Scenario: Admin deletes entry
- **WHEN** an admin sends DELETE `/api/v1/knowledge/entries/{id}`
- **THEN** the system removes the entry from `knowledge_entries_v1` and returns `204 No Content`

#### Scenario: Member reads single entry
- **WHEN** any authenticated client sends GET `/api/v1/knowledge/entries/{id}`
- **THEN** the system returns the full entry including `message_link`

#### Scenario: Entry not found
- **WHEN** a client requests GET `/api/v1/knowledge/entries/{nonexistent-id}`
- **THEN** the system returns `404 Not Found`

---

### Requirement: Paginated Entry Listing
The system SHALL provide GET `/api/v1/knowledge/entries` with pagination and filtering by `tree_path`, `tags`, and `category`.

#### Scenario: Default pagination
- **WHEN** client sends GET `/api/v1/knowledge/entries` without pagination parameters
- **THEN** system returns first 20 entries with `meta.total`, `meta.page`, `meta.per_page`

#### Scenario: Filter by tree_path
- **WHEN** client sends GET `/api/v1/knowledge/entries?tree_path=Technology`
- **THEN** system returns only entries whose `tree_path` starts with `Technology`

#### Scenario: Filter by tag
- **WHEN** client sends GET `/api/v1/knowledge/entries?tag=symfony`
- **THEN** system returns only entries containing `symfony` in their `tags` array
