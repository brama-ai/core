# News Maker Agent

The News Maker Agent is responsible for crawling news sources, extracting articles, ranking them, and rewriting them into Ukrainian-language summaries for the AI community.

## Architecture

The agent consists of several key components:

1. **Crawler** - Fetches and extracts articles from configured news sources
2. **Ranker** - Uses LLM to score and select the most relevant articles
3. **Rewriter** - Converts selected articles into Ukrainian-language summaries
4. **Digest Generator** - Compiles multiple articles into cohesive digest posts
5. **Admin Interface** - Web UI for configuration and manual triggers

## Deep Crawling Feature

### Overview

The deep crawling feature enables the agent to discover articles not just from the base URL of news sources, but also from sub-pages linked from the main page. This significantly increases article discovery without requiring additional source registrations.

### Configuration

Deep crawling is controlled by two main settings available in the admin interface:

- **Crawl Max Depth** (default: 1)
  - `1`: Only crawl the base URL (original behavior)
  - `2`: Crawl base URL + one level of sub-pages

- **Max Links Per Depth** (default: 10)
  - Maximum number of links to follow at each depth level
  - Controls breadth of crawling to prevent excessive HTTP requests

### How It Works

1. **Depth 0**: Fetch the source's base URL and extract up to N links
2. **Depth 1**: For each depth-0 link, fetch the page and:
   - Extract article content if the page is an article
   - Extract additional links for further crawling (if depth < max_depth)
3. **Deduplication**: URLs discovered at multiple depths are only fetched once
4. **Domain Scoping**: Only same-domain or subdomain links are followed
5. **Timeboxing**: Source-level and run-level timeouts prevent runaway crawls

### Database Schema

The deep crawling feature adds provenance tracking to raw news items:

- `crawl_depth` (INTEGER): The depth level where the article was discovered (0 = base URL, 1 = sub-page)
- `discovered_from_url` (VARCHAR): The URL of the page where the link was found

### Performance Considerations

- **HTTP Requests**: At depth 2 with 10 links per depth, worst case is ~111 requests per source
- **Timeboxing**: Source timebox (default 240s) and run timebox (900s) prevent excessive crawling
- **Request Monitoring**: Per-depth HTTP request counters are logged for monitoring

### Configuration Options

#### Environment Variables (config.py)
```python
crawl_max_depth: int = 1                    # Default crawl depth
crawl_max_links_per_depth: int = 10         # Default links per depth
crawl_source_timebox_seconds: int = 240     # Per-source timeout
crawl_run_timebox_seconds: int = 900        # Total run timeout
```

#### Database Settings (AgentSettings)
Runtime configuration via admin UI overrides environment defaults:
- `crawl_max_depth`
- `crawl_max_links_per_depth`

### Migration

The feature was added via Alembic migration `003_add_crawl_depth_columns.py`:
- Adds nullable columns with defaults for backward compatibility
- No data backfill required
- Existing behavior preserved when `max_depth=1`

### Monitoring and Logging

The crawler logs detailed statistics for each source:
- Links discovered at each depth
- HTTP requests made per depth level
- Articles added/existing/failed counts
- Filtering statistics (offsite, static, blocked, etc.)

Example log output:
```
Source 'TechCrunch' depth=1 produced 8 candidate links (href=45 accepted=8 filtered=37 offsite=12 static=15 blocked=3 duplicate=7 invalid=0)
Source 'TechCrunch' done: added=3 existing=2 fetch_failed=1 extract_failed=2 requests_by_depth={0: 1, 1: 8, 2: 15}
```

### Testing

The feature includes comprehensive tests covering:
- Backward compatibility with `max_depth=1`
- Multi-level crawling with mock data
- Depth limit enforcement
- Domain scoping across depths
- Cross-depth deduplication

### Rollback Strategy

To disable deep crawling:
1. Set `crawl_max_depth=1` in admin settings
2. This immediately restores original single-depth behavior
3. Database columns can be dropped in a future migration if needed