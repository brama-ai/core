# PRD: AI Community Platform MVP

## 1. Context

The world of software development is rapidly evolving: we are moving from monoliths and microservices to **agent-oriented systems**. There is a growing need for reliable solutions where Artificial Intelligence doesn't just generate text, but acts as a fully autonomous agent capable of orchestrating tasks, interacting with APIs, and other bots.

Currently, the development of such systems is fragmented. Building hybrid products (where classical backend, API, web frontend, and AI agents seamlessly work together) requires significant infrastructural effort.

## 2. Problem Statement

Developers and businesses face the lack of a single, universal foundation for building agentic systems that:
- Provides agents and microservices the role of flexible "building blocks" (bricks) for systems of any complexity.
- Does not limit technology choices (Language & Framework Agnostic).
- Allows the creation of both pure agent orchestration platforms and complex hybrid web applications with powerful APIs.
- Provides a ready-made infrastructure (a2a communication, event routing) without the need to "reinvent the wheel".

Community users, on the other hand, suffer from information noise and the lack of a single source of truth, as classical moderation tools do not utilize the potential of AI agents.

## 3. Product Goal

Create a universal **platform for building agent-oriented solutions** that unifies classical development and AI. Based on this platform, deploy the MVP of a modular ecosystem for community administration and growth.

Every agent and microservice on the platform is a building block. They can be assembled into anything from a simple automated chatbot to a complex enterprise orchestration platform. The languages and frameworks for agent development can be anything. The core platform provides a2a communication, control over LLM providers (LiteLLM), and execution of complex pipelines.

## 4. Success Criteria

- A single integration with Telegram is active in the chat.
- Agents can be enabled and disabled via commands.
- At least 2 agents create noticeable value during the first pilot.
- The moderator understands exactly why an agent took a specific action.

## 5. MVP Scope

### In Scope

- Single Telegram chat
- Event bus for messages, commands, edits, and deletions
- Agent registry with configurations and `enabled/disabled` status
- Roles: `admin`, `moderator`, `user`
- Postgres as the base storage
- Basic full-text search
- Chat commands for managing agents
- Initial agents:
  - Knowledge Extractor / Community Wiki
  - Locations Catalog
  - News Digest
  - Anti-fraud Signals (lite)

### Out of Scope

- Multi-tenancy
- Web admin panel
- Complex RBAC/ACL model
- External agent marketplace
- Complex external integrations

## 6. Users

### Admin / Owner

Wants to quickly launch modules and control bot behavior without a separate UI.

### Moderator

Wants to reduce noise, mark useful information, and have risk signals.

### Member

Wants to quickly find relevant answers and updates without reading the entire chat.

## 7. Core User Stories

- As an `admin`, I want to see a list of agents and their statuses to manage the platform directly in the chat.
- As an `admin`, I want to enable or disable an agent via a command to quickly test scenarios.
- As a `moderator`, I want to save a useful message to the wiki so knowledge isn't lost.
- As a `member`, I want to find an answer via a command to avoid asking the same questions repeatedly.
- As a `moderator`, I want to see the reasons behind a fraud signal to not rely on a "black box".

## 8. Functional Requirements

### Platform

- Receive events from Telegram.
- Normalize events into an internal format.
- Deliver events to active agents.
- Store the configuration and state of agents.
- Validate access to commands by role.

### Core-Platform Stack

- `PHP 8.5`
- `Symfony 7`
- `Codeception` for testing
- `PHPStan` for static analysis
- `PHP CS Fixer` for code styling
- `GitLab CI` for the CI pipeline
- `glab` for GitLab CLI operations

### Commands

- `/help`
- `/agents`
- `/agent enable <name>`
- `/agent disable <name>`

### Shared Services

- storage
- search
- audit logging
- scheduler for periodic tasks

## 9. Data Model

- `communities(id, name, channel_id, created_at)`
- `agents(id, community_id, name, enabled, config_json, created_at)`
- `messages(id, community_id, platform_msg_id, user_id, text, ts, meta_json)`
- `knowledge(id, community_id, title, body, tags, source_msg_id, created_by, created_at)`
- `locations(id, community_id, name, description, tags, address_text, contact_text, source_msg_id, status, created_by, created_at)`
- `digests(id, community_id, period_start, period_end, body, created_at)`
- `fraud_signals(id, community_id, msg_id, score, reasons_json, created_at)`

## 10. Non-Functional Requirements

- A simple command must respond within 2-3 seconds.
- Automated actions must be explainable.
- No automatic bans in the MVP.
- Structured logs and health checks are mandatory.

## 11. Risks

- Noise from agents if thresholds are too aggressive.
- Low trust in anti-fraud signals.
- Low wiki quality without manual moderation.

## 12. Open Questions

- What is the interface language for the first pilot: UA only or UA/EN?
- Do we need an LLM right at MVP, or launch with rule-based logic?
- Is the digest published automatically, or only manually at launch?
