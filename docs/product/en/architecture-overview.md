# Architecture Overview

## Goal

Create a universal, scalable foundation for **Agentic Solutions**. This architecture allows AI agents and microservices to be used as independent "building blocks" (bricks) to build systems of any complexity: from pure orchestration platforms to complex hybrid web applications with extensive APIs.

The core principle of the architecture is **Language & Framework Agnostic**. The platform's core provides a reliable Event Bus, a2a routing (agent-to-agent), and the infrastructure to connect modules (agents) written in any programming language using any framework, without the need to rewrite the main system.

## Core Components

### 1. Telegram Adapter

- Accepts webhook or polling events.
- Normalizes events into an internal format.
- Maps Telegram user/chat metadata.

### 2. Event Bus

- Routes events to the core and active agents.
- Supported types:
  - `message.created`
  - `message.updated`
  - `message.deleted`
  - `command.received`
  - `schedule.tick`

### 3. Agent Registry

- Registers available agents.
- Stores their manifests.
- Checks whether an agent is enabled for a specific community.

### 4. Command Router

- Parses chat commands.
- Verifies permissions.
- Routes the command to the platform core or a specific agent.

### 5. Shared Services

- Postgres storage layer.
- Redis caching / ephemeral coordination.
- OpenSearch for full-text search across knowledge/messages.
- RabbitMQ for queues / async integration bootstrapping.
- LiteLLM acting as a local proxy / debug gateway for all LLM requests.
- Job scheduler.
- Structured logging.

## Current Local Development Runtime

For local development, a single `docker compose` stack (`ai-community-platform`) is already assembled, integrating:

- `Traefik` as the single public entry layer.
- `core` as the platform-owned HTTP surface at `http://localhost/`.
- `openclaw-stub` as a dedicated runtime placeholder at `http://localhost:8082/`.
- `Postgres` on `localhost:5432`.
- `Redis` on `localhost:6379`.
- `OpenSearch` at `http://localhost:9200/`.
- `RabbitMQ` on `localhost:5672` and `http://localhost:15672/`.
- `LiteLLM` at `http://localhost:4000/`.

This is a local development topology, not the final production deployment model.

## Agent Contract

Every agent must have:

- A `manifest`.
- A `config schema`.
- A list of `supported commands`.
- One or more handlers:
  - `onMessage`
  - `onCommand`
  - `onSchedule`

## Manifest Example

```json
{
  "name": "knowledge-extractor",
  "version": "0.1.0",
  "permissions": ["moderator"],
  "commands": ["/wiki", "/wiki add"],
  "events": ["message.created", "command.received"]
}
```

## MVP Technical Boundaries

- Single runtime process.
- Single database.
- No standalone UI panel.
- No complex asynchronous infrastructure at launch.

Note:
- These constraints describe the MVP application ownership, rather than the number of local dev containers.
- The local environment may feature discrete infrastructure services for development and integration checks.
- LLM calls during local development must route through `LiteLLM`, not directly to the provider API.

## Proposed First Milestone

1. Telegram adapter + command router.
2. Agent registry + enable/disable flow.
3. Shared storage schema.
4. Knowledge Extractor.
5. Locations Catalog.
6. News Digest.
7. Anti-fraud Signals.
