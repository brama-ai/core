## 1. Unified Contract
- [ ] 1.1 Define the shared pipeline-agent context contract for prompt-first execution
- [ ] 1.2 Identify explicit `handoff.md` read exceptions and document them

## 2. Role Migration
- [ ] 2.1 Inventory all current pipeline role prompts (`primary`, `s-*`, existing `u-*`)
- [ ] 2.2 Create canonical `u-*` prompts for each supported role
- [ ] 2.3 Define unified `u-auditor` as a fix-capable post-coder audit role
- [ ] 2.4 Define unified `u-security-review` as a non-fixing security review role that emits remediation follow-up tasks/proposals
- [ ] 2.5 Update Builder and Ultraworks callers to use unified role prompts
- [ ] 2.6 Reorder pipeline execution so `auditor` runs immediately after `coder`
- [ ] 2.7 Ensure validation and automated tests run after auditor remediation and cover both coder and auditor changes
- [ ] 2.8 Remove obsolete duplicated prompt files after parity is confirmed

## 3. Documentation
- [ ] 3.1 Update pipeline model and workflow docs to describe the unified `u-*` layout
- [ ] 3.2 Update any agent matrices that still describe separate `primary` and `s-*` prompt contracts

## 4. Verification
- [ ] 4.1 Validate the OpenSpec change with `openspec validate refactor-unified-pipeline-agents --strict`
- [ ] 4.2 Manually verify that `planner` and `summarizer` remain the only context-contract exceptions unless explicitly extended
- [ ] 4.3 Manually verify that `security-review` findings create structured follow-up remediation work instead of direct code fixes
