## ADDED Requirements

### Requirement: Crawl Depth Configuration
The system SHALL provide admin controls for configuring the maximum crawl depth and the maximum number of links to follow per depth level.

#### Scenario: Admin sets crawl depth to 2
- **WHEN** an admin changes `crawl_max_depth` from 1 to 2 and saves
- **THEN** subsequent crawl runs perform recursive link discovery up to depth 2

#### Scenario: Admin adjusts links per depth
- **WHEN** an admin changes `crawl_max_links_per_depth` to 15 and saves
- **THEN** subsequent crawl runs follow up to 15 links at each depth level

#### Scenario: Default values on fresh setup
- **WHEN** the admin opens crawl settings on a fresh installation
- **THEN** `crawl_max_depth` defaults to 1 and `crawl_max_links_per_depth` defaults to 10
