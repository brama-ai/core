## 1. Agent Repository Documentation (hello-agent)

- [x] 1.1 Add README.md to apps/hello-agent/ with:
  - Agent description and capabilities
  - Prerequisites (PHP 8.5, Composer)
  - Local development setup (`composer install`, `php -S`)
  - Docker standalone run (`docker build -t hello-agent . && docker run -p 8080:80 hello-agent`)
  - GHCR image: `docker pull ghcr.io/nmdimas/a2a-hello-agent:main`
  - Platform integration section (link to compose.fragment.yaml)
  - API endpoints table (/health, /api/v1/manifest, /api/v1/a2a)
  - Environment variables reference
- [x] 1.2 Add compose.fragment.yaml to apps/hello-agent/ following the external agent contract:
  - service name, labels, healthcheck, network, env_file
  - `image: ghcr.io/nmdimas/a2a-hello-agent:main` as default
  - `build:` section for local development override
- [x] 1.3 Add .env.local.example to apps/hello-agent/ documenting all env vars

## 2. Agent Repository Documentation (wiki-agent)

- [x] 2.1 Add README.md to apps/wiki-agent/ with:
  - Agent description and capabilities
  - Prerequisites (Node.js 20, npm)
  - Local development setup
  - Docker standalone run
  - GHCR image: `docker pull ghcr.io/nmdimas/a2a-wiki-agent:main`
  - Platform integration section
  - API endpoints table
  - Environment variables reference
  - Database requirements (PostgreSQL schema, OpenSearch index)
- [x] 2.2 Add compose.fragment.yaml to apps/wiki-agent/ following the external agent contract
- [x] 2.3 Add .env.local.example to apps/wiki-agent/

## 3. Platform Documentation Updates

- [x] 3.1 Update docs/guides/external-agents/en/onboarding.md:
  - Add "GHCR Image-First Workflow" section explaining pull vs build
  - Add example using hello-agent as the reference
  - Document `image:` + `build:` hybrid compose pattern
- [x] 3.2 Update docs/guides/external-agents/en/repository-structure.md:
  - Add Dockerfile best practices for standalone agents
  - Add .github/workflows/docker-publish.yml as recommended CI
  - Add GHCR badge/link convention for README
- [x] 3.3 Update docs/guides/external-agents/ua/ mirrors to match English changes

## 4. Sync Changes to External Repos

- [x] 4.1 Copy updated hello-agent files (README, compose.fragment, .env.local.example) and push to nmdimas/a2a-hello-agent
- [x] 4.2 Copy updated wiki-agent files and push to nmdimas/a2a-wiki-agent

## 5. Quality Checks

- [x] 5.1 Verify compose.fragment.yaml files satisfy convention contract (labels, healthcheck, network)
- [x] 5.2 Verify README instructions work: `docker pull ghcr.io/nmdimas/a2a-hello-agent:main`
- [x] 5.3 Validate openspec change: `openspec validate add-agent-repo-documentation --strict`
