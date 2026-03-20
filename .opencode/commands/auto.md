---
description: "Run Sisyphus-orchestrated pipeline (automatic, parallel)"
agent: build
---

ultrawork Run the full OpenSpec pipeline for this task automatically.

Sisyphus orchestrates all phases: spec → code → reviewer → validate ∥ test → e2e (if UI) → audit loop → docs ∥ summary.
Use s-* subagents for delegation. Read handoff.md between phases.
Initialize handoff.md with task description and profile before starting.
For UI changes: tester checks CUJ matrix (docs/agent-requirements/e2e-cuj-matrix.md) and writes E2E tests.
