## MODIFIED Requirements

### Requirement: Agent Card Fetcher
The platform SHALL fetch Agent Cards from registered agents using the `AgentCardFetcher` service
(was `AgentManifestFetcher`). Discovery SHALL run both on-demand (via admin panel or CLI) and
automatically via the platform scheduler at a 60-second interval.

#### Scenario: Fetch Agent Card from agent
- **WHEN** the platform discovers a new agent via Traefik or Kubernetes provider
- **THEN** the `AgentCardFetcher` retrieves the Agent Card from `http://{hostname}:{port}/api/v1/manifest`

#### Scenario: Scheduled discovery fetches cards automatically
- **WHEN** the platform scheduler triggers `agent:discovery` on its 60-second cycle
- **THEN** the discovery command fetches Agent Cards for all discovered agents and upserts the registry
