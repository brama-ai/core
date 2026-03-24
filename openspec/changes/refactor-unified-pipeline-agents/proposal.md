# Change: Refactor Pipeline Agents To Unified `u-*` Model

## Why
The current pipeline agent layout duplicates role definitions across `primary`, `s-*` subagent, and ad hoc `u-*` variants. This creates drift in model routing, permissions, context handling, and behavior. Recent issues already show the problem: agent prompts diverge, Builder and Ultraworks semantics differ unpredictably, and `.opencode/pipeline/handoff.md` is used inconsistently as hidden context.

The team wants a single consistent agent contract:
- `CONTEXT` from the prompt is primary
- only explicit exceptions may read `handoff.md`
- pipeline agents converge on unified `u-*` role definitions
- non-unified duplicates are removed after migration

## What Changes
- Introduce a unified pipeline-agent architecture based on `u-*` role definitions.
- Define one shared context contract for pipeline agents with prompt-first context handling.
- Restrict `handoff.md` reads to explicit exceptions only, initially `summarizer` and optionally `planner`.
- Migrate Builder and Ultraworks workflows to call unified agents instead of separate `primary` and `s-*` prompt variants where behavior is logically the same.
- Make `auditor` a fix-capable audit phase that runs immediately after `coder` and before validator/tester.
- Require `auditor` to pass remediation context forward so validation and automated tests cover both coder and auditor changes.
- Keep `security-review` non-fixing; if it finds issues, it creates a follow-up remediation task/proposal instead of directly patching code.
- Remove redundant agent prompt files after the unified contract is fully applied.
- Update pipeline documentation and agent matrices to describe the unified model.

## Impact
- Affected specs: `pipeline-agents`
- Affected code: `.opencode/agents/*`, `.opencode/commands/*`, `.opencode/oh-my-opencode.jsonc`, pipeline docs under `brama-core/docs/guides/` and `brama-core/docs/features/`
- Architectural impact: yes, agent execution and prompt-contract model are being standardized
