## ADDED Requirements

### Requirement: Recursive Depth Crawling
The system SHALL support recursive link discovery up to a configurable maximum depth, where depth 0 is the source base URL and each subsequent depth follows links discovered at the previous level.

#### Scenario: Default depth preserves current behavior
- **WHEN** `crawl_max_depth` is set to 1 (default)
- **THEN** the crawler fetches only the base URL and processes links found on that page, identical to the pre-existing single-depth behavior

#### Scenario: Depth 2 discovers sub-page articles
- **WHEN** `crawl_max_depth` is set to 2
- **THEN** the crawler fetches the base URL (depth 0), extracts links, then fetches each depth-0 link and extracts further links (depth 1) for article processing

#### Scenario: Depth limit is enforced
- **WHEN** the crawler reaches the configured `crawl_max_depth`
- **THEN** it does not descend further, even if additional links are found on the deepest pages

#### Scenario: Breadth limit per depth level
- **WHEN** more links are discovered at a given depth than `crawl_max_links_per_depth` allows
- **THEN** only the first `crawl_max_links_per_depth` links are followed at that depth level

### Requirement: Domain Scoping Across Depths
The system SHALL apply the same domain-scoping rules at every crawl depth, ensuring that only same-site or subdomain links are followed.

#### Scenario: Off-domain link at depth 1 is filtered
- **WHEN** a page at depth 0 contains a link to an external domain
- **THEN** that link is excluded from depth-1 crawling

### Requirement: Cross-Depth Deduplication
The system SHALL prevent duplicate processing of URLs discovered at multiple depth levels within the same crawl run.

#### Scenario: URL found at both depths
- **WHEN** the same URL appears in link extraction results at depth 0 and depth 1
- **THEN** the URL is fetched and processed only once, and only one `raw_news_items` record is created

### Requirement: Crawl Provenance Tracking
The system SHALL record the crawl depth and parent URL for each discovered raw news item.

#### Scenario: Depth-0 item records provenance
- **WHEN** an article is extracted from a link found on the source base URL
- **THEN** the `raw_news_items` record stores `crawl_depth = 0` and `discovered_from_url` set to the source base URL

#### Scenario: Depth-1 item records provenance
- **WHEN** an article is extracted from a link found on a depth-0 sub-page
- **THEN** the `raw_news_items` record stores `crawl_depth = 1` and `discovered_from_url` set to the depth-0 page URL

### Requirement: Per-Depth Request Logging
The system SHALL log the number of HTTP requests made at each depth level for every source crawl.

#### Scenario: Depth counters logged after source crawl
- **WHEN** a source crawl completes (successfully or by timebox)
- **THEN** the log entry includes the count of HTTP requests made at each depth level

## MODIFIED Requirements

### Requirement: Raw Temporary Storage
The system SHALL persist parsed source candidates in a temporary raw-news table before editorial processing. Each record includes crawl provenance metadata when available.

#### Scenario: New candidate stored
- **WHEN** the crawler extracts a non-duplicate article candidate
- **THEN** the system inserts a `raw_news_items` record with `status = new`, an `expires_at` timestamp, and optional `crawl_depth` and `discovered_from_url` fields

#### Scenario: Duplicate candidate skipped
- **WHEN** a new candidate matches an existing deduplication hash
- **THEN** the system does not create a second active raw-news record for the same content
