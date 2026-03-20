## ADDED Requirements

### Requirement: RabbitMQ Chunk Consumer
The system SHALL run a KnowledgeWorker process that consumes messages from the `knowledge.chunks` RabbitMQ queue and processes each chunk through the neuron-ai extraction workflow.

#### Scenario: Worker picks up and processes chunk
- **WHEN** a chunk message arrives in `knowledge.chunks`
- **THEN** the worker checks the `processed_chunks` table, runs the extraction workflow if not already completed, and acknowledges the message

#### Scenario: Worker marks chunk completed
- **WHEN** extraction workflow completes successfully
- **THEN** the worker updates `processed_chunks` record to `status = completed` and `processed_at = now()`

#### Scenario: Worker handles extraction failure
- **WHEN** the extraction workflow throws an exception
- **THEN** the worker increments the `attempt_count`, sets `status = failed`, nacks the message with requeue if `attempt_count < 3`, or moves to DLQ if `attempt_count >= 3`

---

### Requirement: Dead Letter Queue Monitoring
The system SHALL expose a count of dead-lettered chunks in the admin settings page and allow admin to inspect and manually requeue them.

#### Scenario: DLQ count shown in admin
- **WHEN** admin views the knowledge agent settings page
- **THEN** the current DLQ message count is displayed

#### Scenario: Admin requeues dead-lettered chunk
- **WHEN** admin selects a DLQ entry and clicks "Повторити обробку"
- **THEN** the system resets `attempt_count` to 0, sets `status = pending`, and moves the message back to `knowledge.chunks`

---

### Requirement: Worker Concurrency and Rate Limiting
The KnowledgeWorker SHALL support configurable concurrent consumers and SHALL respect a per-minute rate limit on LLM API calls to avoid provider throttling.

#### Scenario: Concurrent workers configured
- **WHEN** `KNOWLEDGE_WORKER_CONCURRENCY=3` is set in environment
- **THEN** three parallel consumer processes are started

#### Scenario: Rate limit applied
- **WHEN** LLM API call rate reaches the configured limit (default: 60 requests/minute)
- **THEN** the worker pauses processing and waits until the rate window resets before consuming the next chunk

---

### Requirement: Worker Health Endpoint
The KnowledgeWorker SHALL expose a health check endpoint confirming RabbitMQ connectivity and current processing status.

#### Scenario: Worker healthy
- **WHEN** GET `/health/worker` is called and RabbitMQ is reachable
- **THEN** response is `200 OK` with `{ "status": "healthy", "queue_depth": N, "processing": M }`

#### Scenario: Worker unhealthy — RabbitMQ unreachable
- **WHEN** GET `/health/worker` is called and RabbitMQ connection fails
- **THEN** response is `503 Service Unavailable` with `{ "status": "unhealthy", "reason": "rabbitmq_unreachable" }`
