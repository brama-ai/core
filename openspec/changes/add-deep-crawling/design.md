## Context

The news-maker-agent crawler (`apps/news-maker-agent/app/services/crawler.py`) currently operates at a single depth: it fetches the base URL of each source, extracts up to 20 article links via `_extract_links()`, then fetches and parses each link with `_extract_article()`. Many news sites organize content behind hub/category pages, so a single-depth crawl misses articles that are one navigation step away from the landing page.

This change adds optional 2-level depth crawling while preserving full backward compatibility when `max_depth=1`.

### Stakeholders
- Operators who configure sources and want broader coverage
- The ranking/rewriting pipeline that benefits from a larger candidate pool
- The scheduler that must respect overall timebox constraints

## Goals / Non-Goals

### Goals
- Enable recursive link discovery up to depth 2 (configurable)
- Track crawl provenance (depth level, parent URL) per raw item
- Provide admin controls for depth and breadth limits
- Maintain backward compatibility with `max_depth=1`
- Keep the crawl within existing timebox constraints

### Non-Goals
- Depth > 2 (not needed; complexity grows exponentially)
- Parallel/async HTTP fetching (current synchronous `requests` library is sufficient for MVP)
- Per-source depth overrides (global setting is sufficient for now)
- Changing the deduplication strategy (SHA256 of URL remains correct)

## Decisions

### Decision 1: Iterative BFS within `run_crawl()` instead of recursive function

The crawl loop in `run_crawl()` will use a breadth-first approach with a `depth` counter. For each source:

1. Depth 0: fetch base_url, extract links (up to `max_links_per_depth`)
2. Depth 1: for each depth-0 link, fetch its HTML, extract sub-links (up to `max_links_per_depth`), and also attempt article extraction on the depth-0 page itself
3. All discovered article-candidate URLs (from both depths) are deduplicated and processed

**Rationale**: BFS is simpler to reason about, easier to timebox, and avoids deep call stacks. The `_extract_links()` function already exists and handles domain scoping.

**Alternatives considered**:
- Recursive function: harder to enforce timebox and breadth limits
- Separate crawl queue with worker pattern: over-engineered for depth 2

### Decision 2: Reuse `_extract_links()` for sub-page link extraction

The same `_extract_links()` / `_extract_links_from_html()` functions are used at both depths. Domain scoping (`_is_same_site_or_subdomain`) and static/blocked filtering already apply. The `MAX_LINKS_PER_SOURCE` constant will be replaced by the per-depth limit for the inner loop.

**Rationale**: No new code needed for link filtering; the existing logic is already robust.

### Decision 3: Dynamic source timebox based on depth

When `crawl_max_depth >= 2`, the effective `source_timebox` is read from the `crawl_source_timebox_seconds` config (default stays 120s). The admin can increase it to 240s for deep crawls. The overall `crawl_run_timebox_seconds` (900s) remains unchanged as the hard ceiling.

**Rationale**: Deep crawls need more time per source, but the total run must not exceed the global timebox. Making the source timebox configurable (it already is via `config.py`) is sufficient.

### Decision 4: Two new nullable columns on `raw_news_items`

- `crawl_depth INTEGER DEFAULT 0` — the depth level at which the article was discovered (0 = direct from base_url, 1 = from a sub-page)
- `discovered_from_url VARCHAR(1024) NULL` — the URL of the page where the link was found

Both columns are nullable with defaults, so existing rows are unaffected and the migration is backward-compatible (no data backfill needed).

**Rationale**: Provenance tracking enables future analytics (which depth yields the best articles?) and debugging (why was this article found?).

### Decision 5: Config in both `Settings` (env) and `AgentSettings` (DB)

- `config.py` gets `crawl_max_depth: int = 1` and `crawl_max_links_per_depth: int = 10` as env-level defaults
- `AgentSettings` model gets matching DB columns so operators can change values at runtime via admin UI
- The crawler reads from `AgentSettings` at runtime (same pattern as existing `proxy_url`, `raw_item_ttl_hours`)

**Rationale**: Follows the established pattern where `config.py` provides defaults and `AgentSettings` provides runtime overrides.

### Decision 6: Dual-purpose pages at depth 0

Pages fetched at depth 0 serve two purposes:
1. Link extraction (discover sub-pages)
2. Article extraction (the page itself may be an article)

If `_extract_article()` succeeds on a depth-0 page, it is stored as a raw item with `crawl_depth=0`. This matches current behavior where depth-0 links are already processed as articles.

At depth 1, the same dual-purpose logic applies: each sub-page is both scanned for links (if depth < max_depth) and attempted for article extraction.

## Risks / Trade-offs

### Risk: Increased HTTP request volume
- **Impact**: At depth 2 with 10 links per depth, worst case is 1 (base) + 10 (depth-0 links) + 100 (depth-1 links) = 111 fetches per source
- **Mitigation**: `max_links_per_depth` defaults to 10; source timebox enforces a hard time limit; per-depth request counters enable monitoring

### Risk: Crawling non-article hub pages wastes extraction time
- **Impact**: Some depth-0 links may be category/tag pages with no extractable article content
- **Mitigation**: `_extract_article()` already returns `None` for pages with < 100 chars of text; these are counted as `extract_failed` and skipped quickly

### Risk: Duplicate link discovery across depths
- **Impact**: The same URL may appear at depth 0 and depth 1
- **Mitigation**: The existing `dedup_hash` (SHA256 of URL) prevents duplicate DB inserts; a `seen_urls` set in the crawl loop prevents redundant HTTP fetches

### Risk: Migration on existing data
- **Impact**: Minimal — both new columns are nullable with defaults
- **Mitigation**: No data backfill; existing rows get `crawl_depth=0` and `discovered_from_url=NULL` implicitly

## Migration Plan

1. Create Alembic migration `003_add_crawl_depth_columns.py`:
   - `ALTER TABLE raw_news_items ADD COLUMN crawl_depth INTEGER DEFAULT 0`
   - `ALTER TABLE raw_news_items ADD COLUMN discovered_from_url VARCHAR(1024)`
   - `ALTER TABLE agent_settings ADD COLUMN crawl_max_depth INTEGER DEFAULT 1`
   - `ALTER TABLE agent_settings ADD COLUMN crawl_max_links_per_depth INTEGER DEFAULT 10`
2. Deploy migration before code changes (columns are nullable/defaulted, so old code is unaffected)
3. Deploy code changes — new behavior activates only when admin sets `crawl_max_depth=2`
4. Rollback: set `crawl_max_depth=1` in admin to restore previous behavior; columns can be dropped in a future migration if needed

## Open Questions

- None — the design is straightforward and follows established patterns in the codebase.
