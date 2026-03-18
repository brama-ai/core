## ADDED Requirements

### Requirement: Public Wiki Surface
The system SHALL provide a public wiki web surface owned by `wiki-agent` and exposed through a dedicated Traefik route, not through `core`.

#### Scenario: Public wiki loads from wiki-agent
- **WHEN** a user opens the wiki entrypoint
- **THEN** the request is routed directly to `wiki-agent`
- **AND** the user sees the public `/wiki` page with published content only

#### Scenario: Stable page detail URL
- **WHEN** a user opens `/wiki/page/{slug}`
- **THEN** the system renders the matching published wiki page

### Requirement: Published-Only Public Content
The public wiki SHALL expose only published wiki pages.

#### Scenario: Draft page excluded from public wiki
- **WHEN** a page is stored with `status = draft`
- **THEN** it is not listed on `/wiki`
- **AND** it is not available on `/wiki/page/{slug}`
