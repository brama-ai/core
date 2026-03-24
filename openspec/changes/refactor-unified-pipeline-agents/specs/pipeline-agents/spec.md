## ADDED Requirements
### Requirement: Unified Pipeline Role Definitions

The pipeline SHALL define one canonical `u-*` prompt role for each supported pipeline role instead of maintaining separate duplicated Builder and Sisyphus prompt definitions where the role behavior is logically the same.

Unified role definitions SHALL be reusable across both workflows:
- Builder
- Ultraworks / Sisyphus delegation

#### Scenario: Builder invokes unified architect role
- **WHEN** Builder requires an architecture/spec phase
- **THEN** it invokes the canonical `u-architect` role
- **AND** provides all required task and spec context in the incoming prompt

#### Scenario: Sisyphus invokes unified tester role
- **WHEN** Sisyphus requires a testing phase
- **THEN** it invokes the canonical `u-tester` role
- **AND** provides phase-specific context in the delegation prompt

### Requirement: Prompt-First Context Contract

Pipeline agents SHALL treat the incoming prompt `CONTEXT` as the primary source of truth.

Pipeline agents SHALL NOT read `.opencode/pipeline/handoff.md` unless their role definition explicitly allows it.

If required context is missing from the incoming prompt, the agent SHALL stop and report exactly which required context fields are missing.

#### Scenario: Agent receives complete prompt context
- **WHEN** a pipeline agent is invoked with task goal, expected outcome, changed files, affected apps, and relevant spec paths in `CONTEXT`
- **THEN** the agent proceeds without reading `.opencode/pipeline/handoff.md`

#### Scenario: Agent receives incomplete prompt context
- **WHEN** a pipeline agent is invoked without enough information to perform its role
- **THEN** the agent stops
- **AND** reports the missing context fields instead of guessing

### Requirement: Explicit Handoff Exceptions

Only explicitly designated exception roles SHALL be allowed to read `.opencode/pipeline/handoff.md`.

The system SHALL support the following default exceptions:
- `summarizer`, which may use `handoff.md` as the primary aggregation source
- `planner`, which may read `handoff.md` only for resume or continuity use cases

Non-exception roles SHALL NOT read `.opencode/pipeline/handoff.md` as an implicit context source.

#### Scenario: Summarizer reconciles final status
- **WHEN** summarizer is invoked near pipeline completion
- **THEN** it may read `.opencode/pipeline/handoff.md`
- **AND** use it to reconcile final phase status and summary output

#### Scenario: Non-exception role attempts hidden handoff read
- **WHEN** a non-exception role such as architect or coder runs
- **THEN** it relies on prompt `CONTEXT`
- **AND** does not treat `.opencode/pipeline/handoff.md` as an implicit context source

### Requirement: Auditor Runs As Immediate Post-Coder Quality Gate

The pipeline SHALL run `auditor` immediately after `coder` when the selected workflow includes an audit phase.

The unified `auditor` role SHALL be allowed to apply safe in-scope fixes related to the required task.

The unified `auditor` role SHALL append remediation context describing what it changed, what requirements it verified, and what follow-up coverage is still needed.

If the audit finds issues that still require implementation work after its own safe fixes, the pipeline SHALL return the task to `coder` with explicit remediation context before proceeding.

#### Scenario: Auditor fixes issues after coder
- **WHEN** coder completes an implementation phase
- **AND** the selected workflow includes auditor
- **THEN** auditor runs before validator and tester
- **AND** may apply safe in-scope fixes directly

#### Scenario: Downstream quality covers auditor fixes
- **WHEN** auditor changes code after coder
- **THEN** downstream validation and automated tests run against the combined coder and auditor result
- **AND** the audit remediation details are included in the outgoing context for those phases

#### Scenario: Auditor escalates back to coder
- **WHEN** auditor finds remaining issues that require broader implementation work
- **THEN** it returns explicit remediation context to coder
- **AND** the pipeline resumes implementation before continuing quality phases

### Requirement: Security-Review Creates Remediation Follow-Up Instead Of Direct Fixes

The unified `security-review` role SHALL NOT directly modify source code.

If `security-review` finds issues that require remediation, it SHALL produce structured follow-up work describing the required security change.

If remediation requires contract, behavior, or architecture changes, `security-review` SHALL create or request an OpenSpec proposal/task as the follow-up artifact instead of silently patching the implementation.

#### Scenario: Security-review finds advisory issue
- **WHEN** security-review finds a low-risk issue that does not require contract or architecture changes
- **THEN** it records the issue and remediation guidance in its output
- **AND** does not directly modify source code

#### Scenario: Security-review finds issue requiring spec-driven remediation
- **WHEN** security-review finds an issue whose remediation changes behavior, contracts, or security patterns
- **THEN** it emits a follow-up OpenSpec proposal/task request for the remediation work
- **AND** does not directly patch the code in the current review phase
