# Change: Add Deep Crawling (2-Level Depth) to News-Maker Agent

## Why

The current crawler processes only the base URL of each source, extracting up to 20 links and their articles. This yields shallow coverage — many relevant articles are hidden behind hub/category pages that are one click away from the base URL. Adding recursive crawling with depth 2 (base_url -> hub page -> article page) significantly increases discovery without requiring additional source registrations.

## What Changes

- **Recursive crawl loop in `run_crawl()`** — after extracting links from the base URL (depth 0), each discovered link is also scanned for further links (depth 1), producing a second wave of article candidates
- **New config parameters** — `crawl_max_depth` (default 1 to preserve current behavior) and `crawl_max_links_per_depth` (default 10) control recursion depth and breadth
- **Database schema extension** — two new nullable columns on `raw_news_items`: `crawl_depth` (INTEGER DEFAULT 0) and `discovered_from_url` (VARCHAR 1024) for provenance tracking
- **Alembic migration** — new migration `003_add_crawl_depth_columns.py` adds the columns
- **Admin UI extension** — two new fields on the settings page for `crawl_max_depth` and `crawl_max_links_per_depth`
- **Increased source timebox** — `crawl_source_timebox_seconds` default raised from 120s to 240s when deep crawling is active; overall run timebox stays at 900s
- **Per-depth HTTP request counters** — structured logging of requests made at each depth level

## Impact

- Affected specs: `news-ingestion`, `news-admin`
- Affected code: `apps/news-maker-agent/app/services/crawler.py`, `apps/news-maker-agent/app/models/models.py`, `apps/news-maker-agent/app/config.py`, `apps/news-maker-agent/app/routers/admin/settings.py`, `apps/news-maker-agent/alembic/versions/`, `apps/news-maker-agent/tests/test_crawler.py`
- No API contract changes (A2A endpoints unchanged)
- Backward compatible: `max_depth=1` reproduces current behavior exactly
