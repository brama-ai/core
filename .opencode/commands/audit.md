---
description: "Run audit loop: find issues → fix → re-audit (max 3 iterations)"
agent: build
---

ultrawork Run audit loop only — Phase 4.

1. Delegate s-auditor (read-only, Opus) to find issues
2. If FAIL: generate fix tasks, delegate s-coder to fix
3. Re-audit. Max 3 iterations.
4. Delegate s-summarizer for audit summary.
