# Tasks: add-wiki-agent-foundation

## 1. Runtime and Routing

- [x] 1.1 Create a new TypeScript `wiki-agent` service
- [x] 1.2 Add Dockerfile and compose fragment for `wiki-agent`
- [x] 1.3 Add a dedicated Traefik entrypoint and route traffic directly to `wiki-agent`

## 2. Storage and Search

- [x] 2.1 Create Postgres schema bootstrap for `wiki_agent`
- [x] 2.2 Add wiki page CRUD storage with publish/draft state
- [x] 2.3 Add OpenSearch indexing for published pages
- [x] 2.4 Add OpenSearch search with Postgres fallback

## 3. Public and Admin Surfaces

- [x] 3.1 Add public `/wiki` list/search page
- [x] 3.2 Add public `/wiki/page/{slug}` detail page
- [x] 3.3 Add separate `wiki-admin` login and CRUD UI
- [x] 3.4 Add a simple visual editor for wiki page body

## 4. Grounded Chat and Agent Contract

- [x] 4.1 Add grounded chat endpoint for public wiki usage
- [x] 4.2 Add embedded chat panel to the public wiki UI
- [x] 4.3 Expose `/health`, `/api/v1/manifest`, and `/api/v1/a2a`

## 5. Documentation and Verification

- [x] 5.1 Add OpenSpec proposal and spec deltas
- [x] 5.2 Add agent docs in `docs/`
- [x] 5.3 Install Node dependencies and run `npm run build`
- [x] 5.4 Run `npm test`
