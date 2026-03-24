# A2A Terminology Mapping

This document maps our platform terminology to the official [A2A Protocol](https://a2a-protocol.org) specification terms.

## Terminology Table

| Platform Term | Official A2A Term | Location in Codebase | Notes |
|---|---|---|---|
| Agent Card | Agent Card | `GET /api/v1/manifest` response | JSON metadata document describing agent identity, skills, and endpoint |
| `url` | `url` | Agent Card field | A2A Server endpoint URL (replaces deprecated `a2a_endpoint`) |
| `provider` | AgentProvider | Agent Card field | `{ organization, url }` — service provider information |
| `capabilities` | AgentCapabilities | Agent Card field | `{ streaming, pushNotifications, stateTransitionHistory }` — A2A protocol features |
| Skills | AgentSkill | `skills` field in Agent Card | Structured objects: `{ id, name, description, tags, examples }` |
| `defaultInputModes` | `defaultInputModes` | Agent Card field | MIME types for input (default: `["text"]`) |
| `defaultOutputModes` | `defaultOutputModes` | Agent Card field | MIME types for output (default: `["text"]`) |
| Skill Schemas | — | `skill_schemas` field in Agent Card | Deprecated: per-skill JSON Schema for input validation. Fold into structured skills |
| Skill Catalog | — | `GET /api/v1/a2a/discovery` | Aggregated list of all skills from enabled agents |
| A2A Gateway | — | `App\A2AGateway` namespace | Core's dual role as A2A Server + A2A Client |
| A2A Client | A2A Client | `App\A2AGateway\A2AClient` | Core component that calls agent A2A endpoints |
| A2A Server | A2A Server | Each agent's `POST /api/v1/a2a` handler | Agent-side handler for incoming A2A requests |
| Send Message | tasks/send | `POST /api/v1/a2a/send-message` | Core's inbound A2A endpoint (from OpenClaw) |
| Agent Card Fetcher | — | `App\A2AGateway\AgentCardFetcher` | Fetches Agent Card from agent's manifest endpoint |
| A2A Message Audit | — | `a2a_message_audit` table | Audit log for all A2A interactions |
| Well-Known Discovery | `/.well-known/agent-card.json` | Core endpoint | Platform-level AgentCard with aggregated skills |

## Architecture Roles

```
OpenClaw (A2A Client)
    ↓ POST /api/v1/a2a/send-message
Core (A2A Gateway)
    ↓ POST /api/v1/a2a (per agent)
Agents (A2A Servers)
```

- **OpenClaw** sends A2A requests to Core
- **Core** acts as an A2A Gateway — validates, routes, audits, and observes
- **Agents** are A2A Servers that process skills and return structured responses

## Discovery Endpoints

| Endpoint | Scope | Auth | Purpose |
|---|---|---|---|
| `GET /.well-known/agent-card.json` | Platform | Public | Official A2A discovery — returns Core's AgentCard with aggregated skills |
| `GET /api/v1/a2a/discovery` | Platform | Bearer token | Detailed skill catalog for OpenClaw integration |
| `GET /api/v1/manifest` | Per agent | None | Agent-level Agent Card (internal, fetched by Core during discovery) |

## Key Design Decisions

1. **Agent manifest endpoint stable**: Agents serve Agent Cards at `GET /api/v1/manifest` (no rename to avoid breaking existing agents)
2. **`url` replaces `a2a_endpoint`**: The `url` field aligns with official A2A `AgentCard.url`. Legacy `a2a_endpoint` accepted for backward compatibility
3. **Structured skills**: Skills are now `AgentSkill` objects `{ id, name, description, tags }`. Legacy string skills accepted and auto-normalized
4. **`capabilities` is A2A AgentCapabilities**: Not to be confused with the old `capabilities` field (which was renamed to `skills` in the terminology refactoring)
5. **`intent` kept in payload**: The A2A request body uses `intent` alongside `tool` for backward compatibility
6. **Agent Card schema**: Defined in `apps/brama-core/config/agent-card.schema.json`
