# Tasks: Fix Remaining E2E Test Failures

## Task 1: Fix Agent Health Badge Assertions
- [ ] Inspect badge HTML on agents page (take screenshot, read DOM)
- [ ] Identify all possible badge CSS classes from template (`agents.html.twig`)
- [ ] Update `AgentsPage.js` `seeAgentHealthy()` and `seeAgentHealthyLike()` to accept all valid states
- [ ] Verify: `knowledge-agent`, `news-maker-agent`, `hello-agent` badge tests pass

**Files:** `support/pages/AgentsPage.js`, `tests/admin/agents_test.js`, `tests/admin/hello_agent_test.js`

## Task 2: Fix OpenSearch Seeding for Log Trace
- [ ] Add `?refresh=true` to OpenSearch bulk insert in `log_trace_test.js`
- [ ] Verify index name matches `LogIndexManager::todayIndexName()` format
- [ ] Add explicit `_refresh` call after seeding if needed
- [ ] Verify: `setup: seed test trace data` passes

**Files:** `tests/admin/log_trace_test.js`

## Task 3: Fix OpenSearch Seeding for Wiki
- [ ] Add `?refresh=true` to OpenSearch index calls in `wiki_encyclopedia_test.js`
- [ ] Verify index name matches what wiki-agent queries
- [ ] Check if wiki-agent uses a different OpenSearch index than `knowledge_agent_knowledge_entries_test`
- [ ] Verify: All 7 wiki tests pass

**Files:** `tests/knowledge/wiki_encyclopedia_test.js`

## Task 4: Fix Knowledge Tree Panel Filter
- [ ] Add `?refresh=true` to OpenSearch seeding in `admin_crud_test.js`
- [ ] Verify tree panel queries match seeded data structure
- [ ] Verify: `can filter entries via tree panel` passes

**Files:** `tests/knowledge/admin_crud_test.js`

## Task 5: Fix News Crawl Trigger UI Feedback
- [ ] Inspect `#crawlTriggerBtn` click handler JS in agent_settings template
- [ ] Check if fetch URL is correct in iframe context (relative vs absolute)
- [ ] Add browser console log capture to debug fetch failures
- [ ] Fix JS or test to handle the actual response format
- [ ] Verify: both crawl trigger tests pass

**Files:** `tests/admin/news_maker_admin_test.js`, `tests/admin/news_digest_pipeline_test.js`

## Task 6: Fix Scheduler Logs Pagination Counter
- [ ] Inspect scheduler logs page DOM for total entries element
- [ ] Fix selector to match actual element
- [ ] Verify: `job logs page shows pagination for many entries` passes

**Files:** `tests/admin/scheduler_logs_test.js`
