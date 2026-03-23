# Proposal: Fix Remaining E2E Test Failures

## Status: draft

## Problem

After fixing 45 of 60 failing E2E tests, 15 tests still fail. These require deeper investigation into service configuration, OpenSearch indexing timing, and Traefik API pagination. The failures fall into distinct root-cause categories that each need targeted fixes.

## Current State

**192 passed, 15 failed, 1 skipped** out of 208 total E2E tests (92% pass rate).

## Failing Tests by Root Cause

### Category 1: Agent Health Badges (4 tests)
**Files:** `agents_test.js`, `hello_agent_test.js`, `news_maker_test.js`
- knowledge-agent, news-maker-agent, hello-agent show neither `badge-healthy` nor `badge-degraded`
- **Root cause:** Agent health check returns a status that maps to a different CSS class (e.g., `badge-warning`, `badge-unknown`). Need to inspect actual badge HTML and either fix the agent health endpoint or broaden the assertion.

### Category 2: OpenSearch Seeding Timing (8 tests)
**Files:** `log_trace_test.js`, `wiki_encyclopedia_test.js`, `admin_crud_test.js`
- Log trace setup: OpenSearch bulk insert via docker exec may not index in time
- Wiki tests (7): Seeded entries via OpenSearch don't appear in wiki view
- Knowledge tree filter: Seeded entries not visible in tree panel
- **Root cause:** OpenSearch needs explicit `_refresh` after bulk insert, or the index name pattern doesn't match what the app queries. Need to add `?refresh=true` parameter to bulk API calls and verify index naming convention.

### Category 3: News Crawl Trigger (2 tests)
**Files:** `news_maker_admin_test.js`, `news_digest_pipeline_test.js`
- `#crawlTriggerResult` element exists but never becomes visible after clicking trigger button
- **Root cause:** The JS fetch to `/admin/trigger/crawl` may fail silently (CORS, auth, or network error in iframe context). Need to inspect browser console logs during test and verify the endpoint responds correctly.

### Category 4: Scheduler Pagination (1 test)
**File:** `scheduler_logs_test.js`
- Total entries count span not found
- **Root cause:** The text "записів" may be in a different element than expected. Need to inspect actual scheduler logs page DOM.

## Proposed Solution

Fix each category with targeted changes:

1. **Agent badges:** Read actual badge HTML from screenshot, update XPath to match all valid badge states
2. **OpenSearch seeding:** Add `?refresh=true` to all OpenSearch bulk/index API calls; verify index name patterns match app queries
3. **News crawl trigger:** Debug JS fetch in iframe context; consider using direct API call instead of UI button
4. **Scheduler pagination:** Inspect DOM and fix selector

## Impact

- Zero failing tests (target: 208/208 passing)
- No feature changes, test-only fixes
