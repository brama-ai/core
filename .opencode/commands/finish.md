---
description: "Resume pipeline from current state in handoff.md"
agent: build
---

ultrawork Read `.opencode/pipeline/handoff.md` and determine what phases remain.

Run ONLY the phases that haven't completed yet. Skip already-done phases.
If reviewer hasn't run after coder → run reviewer.
If validator/tester haven't run → run them parallel.
If auditor needed but not run → run audit loop.
Always end with s-summarizer.
