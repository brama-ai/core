## 1. Database Schema

- [x] 1.1 Add `crawl_depth` and `discovered_from_url` columns to `RawNewsItem` model in `apps/news-maker-agent/app/models/models.py`
- [x] 1.2 Add `crawl_max_depth` and `crawl_max_links_per_depth` columns to `AgentSettings` model in `apps/news-maker-agent/app/models/models.py`
- [x] 1.3 Create Alembic migration `003_add_crawl_depth_columns.py` in `apps/news-maker-agent/alembic/versions/` that adds all four new columns

## 2. Configuration

- [x] 2.1 Add `crawl_max_depth: int = 1` and `crawl_max_links_per_depth: int = 10` to `Settings` in `apps/news-maker-agent/app/config.py`

## 3. Crawler Logic

- [x] 3.1 Refactor `run_crawl()` in `apps/news-maker-agent/app/services/crawler.py` to read `crawl_max_depth` and `crawl_max_links_per_depth` from `AgentSettings` (falling back to config defaults)
- [x] 3.2 Implement BFS crawl loop: after extracting depth-0 links from base_url, iterate discovered links and call `_extract_links()` on each to find depth-1 candidates (when `max_depth >= 2`)
- [x] 3.3 Maintain a `seen_urls: set[str]` across all depths to avoid redundant HTTP fetches
- [x] 3.4 Pass `crawl_depth` and `discovered_from_url` when creating `RawNewsItem` records
- [x] 3.5 Add per-depth HTTP request counters and log them at the end of each source crawl
- [x] 3.6 Respect `source_timebox` across all depth levels (existing deadline check applies to inner loops)

## 4. Admin UI

- [x] 4.1 Add `crawl_max_depth` and `crawl_max_links_per_depth` fields to the admin settings form in `apps/news-maker-agent/app/routers/admin/settings.py`
- [x] 4.2 Update the admin settings HTML template to include the two new fields with appropriate labels and defaults

## 5. Tests

- [x] 5.1 Verify existing tests in `apps/news-maker-agent/tests/test_crawler.py` still pass with `max_depth=1` (no behavioral change)
- [x] 5.2 Add test: recursive crawling with mock data at 2 levels — verify articles from both depths are discovered
- [x] 5.3 Add test: depth limit enforcement — verify crawler does not descend to depth 3 when `max_depth=2`
- [x] 5.4 Add test: domain scoping at depth 1 — verify off-domain links discovered at depth 1 are filtered out
- [x] 5.5 Add test: deduplication across depths — verify a URL found at both depth 0 and depth 1 results in only one raw item

## 6. Documentation

- [x] 6.1 Update or create developer documentation in `docs/` describing the deep crawling feature and configuration options
