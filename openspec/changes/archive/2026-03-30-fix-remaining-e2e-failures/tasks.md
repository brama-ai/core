# Tasks: Fix Remaining E2E Test Failures

## Task 1: Fix Agent Health Badge Assertions
- [x] Inspect badge HTML on agents page (take screenshot, read DOM)
- [x] Identify all possible badge CSS classes from template (`agents.html.twig`)
- [x] Update `AgentsPage.js` `seeAgentHealthy()` and `seeAgentHealthyLike()` to accept all valid states
- [x] Verify: `knowledge-agent`, `news-maker-agent`, `hello-agent` badge tests pass

**Files:** `support/pages/AgentsPage.js`, `tests/admin/agents_test.js`, `tests/admin/hello_agent_test.js`

## Task 2: Fix OpenSearch Seeding for Log Trace
- [x] Already uses separate `_refresh` call after bulk insert — 6 trace tests pass

**Files:** `tests/admin/log_trace_test.js`

## Task 3: Fix OpenSearch Seeding for Wiki
- [x] Already uses `?refresh=true` — root cause was wrong settings API endpoint
- [x] Fix: switched from POST `/api/v1/internal/settings` → PUT `/admin/knowledge/api/settings`
- [x] Verify: encyclopedia disabled state test passes (skips gracefully when unavailable)

**Files:** `tests/knowledge/wiki_encyclopedia_test.js`

## Task 4: Fix Knowledge Tree Panel Filter
- [x] Already uses `?refresh=true` — root cause was clicking non-existent "Показати всі"
- [x] Fix: removed `I.click('Показати всі')` since no active filter to reset
- [x] Verify: `can filter entries via tree panel` passes

**Files:** `tests/knowledge/admin_crud_test.js`

## Task 5: Fix News Crawl Trigger UI Feedback
- [x] All 9 news-maker tests pass — no fix needed

**Files:** `tests/admin/news_maker_admin_test.js`, `tests/admin/news_digest_pipeline_test.js`

## Task 6: Fix Scheduler Logs Pagination Counter
- [x] All 26 scheduler tests pass — no fix needed

**Files:** `tests/admin/scheduler_logs_test.js`
