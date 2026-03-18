# Change: Add Wiki Agent Foundation

## Why

The repository already contains a `knowledge-agent`, but the desired wiki experience must be implemented without changing that service or reworking its code path. The product needs a separate `wiki-agent` that owns a public wiki, a dedicated `wiki-admin` surface, and a grounded AI chat for the hackathon deliverable.

## What Changes

- Add a new standalone `wiki-agent` implemented in TypeScript
- Route Traefik directly to `wiki-agent` through a dedicated `wiki` entrypoint
- Add public `/wiki` and `/wiki/page/{slug}` routes owned by `wiki-agent`
- Add a separate `/wiki-admin` login and CRUD interface, not under shared `/admin`
- Store wiki pages in a dedicated Postgres schema on shared infra
- Index published pages into a dedicated OpenSearch index namespace
- Add grounded wiki chat that answers only from published wiki pages
- Expose required agent endpoints: `/health`, `/api/v1/manifest`, `/api/v1/a2a`

## Impact

- Affected specs: `wiki-web`, `wiki-admin`, `wiki-chat`, `agent-conventions`
- Affected code: new `apps/wiki-agent/`, new `docker/wiki-agent/`, new `compose.agent-wiki.yaml`, Traefik config, local-dev docs
- Shared infra reused: Postgres, OpenSearch, RabbitMQ, LiteLLM
- New isolated resources: Postgres schema `wiki_agent`, OpenSearch index `wiki_agent_pages`, RabbitMQ namespace `wiki_agent.*`
