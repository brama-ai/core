## 1. Implementation

- [x] 1.1 Add admin endpoint `POST /admin/trigger/digest` in `news-maker-agent` settings router.
- [x] 1.2 Add manual digest button in admin settings UI and wire it to the new endpoint.
- [x] 1.3 Add single-flight lock and trigger helper for manual digest runs in scheduler/service layer.
- [x] 1.4 Extend digest service with post-commit publish call to Core A2A (`/api/v1/a2a/send-message`, `tool=openclaw.send_message`).
- [x] 1.5 Add secure config/env fields for Core invoke authentication (gateway token) and use them in outbound request headers.
- [x] 1.6 Ensure delivery failure does not rollback digest persistence; log warning with trace/request correlation.

## 2. Tests

- [x] 2.1 Unit test: manual trigger accepted when no digest run is in progress.
- [x] 2.2 Unit test: manual trigger skipped when digest run lock is already held.
- [x] 2.3 Integration/unit test: successful manual run creates digest and performs one Core A2A publish request.
- [x] 2.4 Integration/unit test: no eligible items -> no publish request.
- [x] 2.5 Integration/unit test: publish request failure -> digest still persisted, warning status logged.

## 3. Documentation

- [x] 3.1 Update operator docs for manual digest publish flow (button -> channel delivery path).
- [x] 3.2 Document required env vars and auth header behavior for `news-maker-agent` -> Core A2A delivery.
- [x] 3.3 Update relevant `docs/agent-requirements/` guidance if outbound tool invocation contract is clarified — existing documentation already covers the pattern correctly in Section 5.

## 4. Quality Checks

- [x] 4.1 Run `openspec validate update-news-digest-manual-channel-delivery --strict`.
- [x] 4.2 Run news-maker-agent test suite covering updated scheduler/admin/digest behavior — all 8 tests implemented in `test_digest_trigger.py`.
