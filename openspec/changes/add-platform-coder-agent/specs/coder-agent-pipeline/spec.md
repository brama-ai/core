## ADDED Requirements

### Requirement: Multi-Stage Pipeline Execution

The system SHALL execute coding tasks through a sequential 5-stage pipeline: architect, coder, validator, tester, documenter. Each stage SHALL be executed as a subprocess calling an AI coding tool via the LiteLLM gateway. The orchestrator SHALL capture all stdout/stderr output and persist it to the task log. Each stage SHALL have a configurable timeout with a default matching the existing pipeline configuration (architect: 45min, coder: 60min, validator: 20min, tester: 30min, documenter: 15min).

#### Scenario: Full pipeline execution succeeds
- **WHEN** a task with all 5 stages enabled is dequeued by a worker
- **THEN** the pipeline orchestrator runs architect, coder, validator, tester, and documenter in sequence
- **AND** each stage passes its gate check before the next stage begins
- **AND** the task status transitions from `in_progress` to `done`
- **AND** all stage logs are persisted to `coder_task_logs`

#### Scenario: Pipeline with skipped stages
- **WHEN** a task has `pipeline_config.skip_stages` set to `["architect", "documenter"]`
- **THEN** only coder, validator, and tester stages are executed
- **AND** skipped stages are recorded as `skipped` in `stage_progress`

### Requirement: Stage Gate Verification

The system SHALL run a gate check after each pipeline stage completes. Each gate check SHALL verify that the stage produced valid output. If a gate check fails, the stage SHALL be retried up to `MAX_RETRIES` times (default: 2). If all retries fail, the task SHALL move to `failed` status with the error message recorded.

#### Scenario: Gate check passes
- **WHEN** the validator stage completes and PHPStan reports zero errors and CS Fixer reports no violations
- **THEN** the gate check passes
- **AND** the pipeline proceeds to the tester stage

#### Scenario: Gate check fails with retries remaining
- **WHEN** the coder stage completes but the gate check detects PHP syntax errors
- **AND** retry count is less than MAX_RETRIES
- **THEN** the coder stage is re-executed with the error context appended to the prompt
- **AND** retry count is incremented

#### Scenario: Gate check fails with no retries remaining
- **WHEN** a stage gate check fails
- **AND** retry count equals MAX_RETRIES
- **THEN** the task status moves to `failed`
- **AND** the error message and last stage output are recorded

### Requirement: Model Selection with Fallback Chains

The system SHALL select an AI model for each pipeline stage based on a configurable fallback chain. If the primary model is unavailable or returns an error, the system SHALL try the next model in the chain. Each stage SHALL have a default fallback chain matching the existing pipeline configuration. Per-task model overrides SHALL be supported via the `pipeline_config.model_overrides` field.

#### Scenario: Primary model succeeds
- **WHEN** the architect stage starts with default configuration
- **THEN** the system uses the first model in the architect fallback chain
- **AND** the model used is recorded in the stage log metadata

#### Scenario: Primary model fails, fallback succeeds
- **WHEN** the coder stage starts and the primary model returns an error
- **THEN** the system tries the next model in the fallback chain
- **AND** if the fallback model succeeds, the stage completes normally
- **AND** both the failed attempt and successful attempt are logged

#### Scenario: Per-task model override
- **WHEN** a task has `pipeline_config.model_overrides.coder` set to `claude-opus-4-6`
- **THEN** the coder stage uses `claude-opus-4-6` as the primary model instead of the default

### Requirement: Inter-Stage Handoff

The system SHALL generate a handoff document between pipeline stages containing the task description, previous stage outputs, and accumulated context. The handoff format SHALL follow the existing `handoff-template.md` convention from the bash pipeline. Each stage SHALL receive the handoff as input context.

#### Scenario: Handoff from architect to coder
- **WHEN** the architect stage completes successfully
- **THEN** a handoff document is generated containing the OpenSpec proposal, task description, and architect output
- **AND** the coder stage receives this handoff as input context
