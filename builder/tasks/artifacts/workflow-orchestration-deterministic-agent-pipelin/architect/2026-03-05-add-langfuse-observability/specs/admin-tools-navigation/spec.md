## ADDED Requirements

### Requirement: Admin Tools Sidebar Section
The admin sidebar SHALL include a dedicated `Інструменти` section for operator-facing platform tools.

#### Scenario: Admin opens protected area
- **WHEN** an authenticated admin opens any `/admin/*` page
- **THEN** the left sidebar SHALL render the `Інструменти` section

### Requirement: Langfuse Navigation from Admin
The admin UI SHALL provide a visible navigation link/button to Langfuse.

#### Scenario: Admin navigates to observability tool
- **WHEN** an admin clicks the Langfuse link in the `Інструменти` section
- **THEN** the browser SHALL open the configured Langfuse URL
