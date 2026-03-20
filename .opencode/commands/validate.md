---
description: "Run quality gate: static analysis + tests in parallel"
agent: build
---

ultrawork Run quality gate only — validator and tester in parallel.

Phase 4 only:
- delegate s-validator with run_in_background=true
- delegate s-tester with run_in_background=true
Wait for both. Report results. No other phases.
