## ADDED Requirements

### Requirement: Embedding Computation on Curated Item Creation
The system SHALL compute a vector embedding for each newly created curated news item using the configured embedding model via LiteLLM.

#### Scenario: Curated item receives embedding
- **WHEN** the rewriter creates a new curated item with `status = ready`
- **THEN** the system computes an embedding from the item's title + summary and stores it in the `embedding` column

#### Scenario: Embedding model is configurable
- **WHEN** an admin updates the embedding model in agent settings
- **THEN** subsequent embedding computations use the new model

### Requirement: Similarity Search Against Recent Items
The system SHALL compare each new curated item's embedding against existing items from the last 2 months using cosine similarity.

#### Scenario: Similar item found above threshold
- **WHEN** the cosine similarity between a new item and an existing item exceeds 0.85
- **THEN** the system triggers LLM-based duplicate confirmation for the pair

#### Scenario: No similar items found
- **WHEN** no existing item within the 2-month window exceeds the similarity threshold
- **THEN** the item retains `status = ready` and is eligible for digest

#### Scenario: Lookback window is bounded to 2 months
- **WHEN** the similarity search runs
- **THEN** only curated items created within the last 2 months are included in the comparison set

### Requirement: LLM-Based Duplicate Confirmation
The system SHALL use an LLM call to confirm whether two semantically similar items are true duplicates covering the same event or topic.

#### Scenario: LLM confirms duplicate
- **WHEN** the LLM determines two items describe the same news event
- **THEN** the new item's status is set to `duplicate` and it is excluded from digest generation

#### Scenario: LLM rejects duplicate
- **WHEN** the LLM determines two items cover distinct events despite high embedding similarity
- **THEN** the new item retains `status = ready`

#### Scenario: LLM dedup call fails gracefully
- **WHEN** the LLM call for duplicate confirmation fails (timeout, error)
- **THEN** the item retains `status = ready` and the failure is logged
