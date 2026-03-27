## ADDED Requirements

### Requirement: Deployer Pipeline Agent

The pipeline SHALL include a `deployer` agent that takes completed, validated pipeline output and deploys it to the target environment using a configured deployment strategy.

The deployer agent SHALL be available in both workflows:
- Ultraworks: `s-deployer` subagent delegated by Sisyphus
- Unified: `deployer` primary agent

The deployer agent SHALL support four deployment strategies:

| Strategy | Description |
|----------|-------------|
| `pr-only` | Push branch and create a GitHub PR via `gh pr create`. Default and safest strategy. |
| `merge-and-deploy` | Create PR, enable auto-merge via `gh pr merge --auto`, wait for CI-driven deployment. |
| `direct-ssh` | SSH to server via MCP SSH, `git pull`, `docker compose up -d --build`. For Docker Compose servers. |
| `helm-upgrade` | SSH to server via MCP SSH, update image tags, run `helm upgrade --install`. For K3s/Kubernetes servers. |

The deployer agent SHALL only execute when explicitly requested — it MUST NOT run automatically on every pipeline task.

The deployer agent SHALL verify that all previous pipeline stages completed successfully before proceeding. If any stage failed, the deployer MUST refuse to deploy and report the failure.

#### Scenario: PR-only deployment
- **WHEN** deployer is invoked with strategy `pr-only`
- **AND** all previous pipeline stages passed
- **THEN** deployer pushes the current branch to the remote
- **AND** creates a GitHub PR via `gh pr create` with a summary of changes
- **AND** reports the PR URL in the pipeline handoff

#### Scenario: Merge-and-deploy via CI
- **WHEN** deployer is invoked with strategy `merge-and-deploy`
- **AND** all previous pipeline stages passed
- **THEN** deployer creates a GitHub PR and enables auto-merge via `gh pr merge --auto`
- **AND** waits for CI to complete the deployment
- **AND** verifies deployment health by curling the health endpoint

#### Scenario: Direct SSH deployment
- **WHEN** deployer is invoked with strategy `direct-ssh`
- **AND** all previous pipeline stages passed
- **AND** SSH credentials are configured in `.devcontainer/.ssh-env`
- **THEN** deployer connects to the target server via MCP SSH
- **AND** navigates to the application path, pulls latest changes, and runs `docker compose up -d --build`
- **AND** verifies deployment health by curling the health endpoint

#### Scenario: Helm upgrade deployment
- **WHEN** deployer is invoked with strategy `helm-upgrade`
- **AND** all previous pipeline stages passed
- **AND** SSH credentials are configured in `.devcontainer/.ssh-env`
- **THEN** deployer connects to the target server via MCP SSH
- **AND** updates image tags and runs `helm upgrade --install` with the appropriate values file
- **AND** verifies rollout status via `kubectl rollout status`
- **AND** verifies deployment health by curling the health endpoint

#### Scenario: Deployment refused when stages failed
- **WHEN** deployer is invoked
- **AND** one or more previous pipeline stages reported failure
- **THEN** deployer MUST refuse to deploy
- **AND** report which stages failed and why deployment was blocked

### Requirement: Deployer Safety Gates

The deployer agent SHALL enforce safety gates to prevent accidental or unauthorized deployments.

The deployer MUST require explicit opt-in via `deploy: true` in task metadata or pipeline configuration. Without this flag, the deployer MUST NOT execute any deployment actions.

The deployer SHALL default to dry-run mode. In dry-run mode, the deployer MUST show what actions it would take without executing them.

The deployer MUST NOT force-push to any branch.

The deployer MUST NOT deploy to production without explicit confirmation in the pipeline configuration.

The deployer SHALL document a rollback plan before executing any destructive deployment action (strategies: `direct-ssh`, `helm-upgrade`).

#### Scenario: Dry-run mode by default
- **WHEN** deployer is invoked without explicit `dry_run: false` in configuration
- **THEN** deployer runs in dry-run mode
- **AND** reports all planned actions without executing them
- **AND** includes the rollback plan in the report

#### Scenario: Explicit opt-in required
- **WHEN** a pipeline task does not include `deploy: true` in its metadata
- **THEN** deployer MUST skip the deployment phase entirely
- **AND** report that deployment was skipped due to missing opt-in

#### Scenario: Force-push prevention
- **WHEN** deployer pushes a branch to the remote
- **THEN** deployer MUST use regular `git push` without `--force` or `--force-with-lease`
- **AND** if the push is rejected, deployer reports the conflict and stops

### Requirement: Deployer SSH Integration

The deployer agent SHALL reuse MCP SSH agent configuration from `.devcontainer/.ssh-env` for server access.

The deployer MUST NOT store, log, or expose SSH credentials in pipeline output, handoff files, or commit messages.

The deployer SHALL verify server connectivity before attempting deployment actions.

#### Scenario: SSH connection using existing configuration
- **WHEN** deployer needs to connect to a server for `direct-ssh` or `helm-upgrade` strategy
- **THEN** deployer reads SSH configuration from `.devcontainer/.ssh-env`
- **AND** establishes a connection via the MCP SSH tools
- **AND** verifies connectivity before proceeding with deployment commands

#### Scenario: SSH connection failure
- **WHEN** deployer attempts to connect to the target server
- **AND** the connection fails (timeout, auth failure, unreachable)
- **THEN** deployer reports the connection error
- **AND** does not attempt any deployment actions
- **AND** marks the deployment as failed in the pipeline handoff

### Requirement: Deployer Health Verification

The deployer agent SHALL verify deployment health after executing any deployment action that modifies the running environment (strategies: `direct-ssh`, `helm-upgrade`, `merge-and-deploy`).

Health verification SHALL consist of curling the application health endpoint and checking for a successful response.

#### Scenario: Health check passes after deployment
- **WHEN** deployer completes a deployment action
- **AND** curls the configured health endpoint
- **AND** receives a 200 OK response
- **THEN** deployer reports deployment as successful in the pipeline handoff

#### Scenario: Health check fails after deployment
- **WHEN** deployer completes a deployment action
- **AND** curls the configured health endpoint
- **AND** does not receive a 200 OK response within the configured timeout
- **THEN** deployer reports deployment as failed
- **AND** includes the rollback plan in the failure report
- **AND** marks the deployment as requiring manual intervention

### Requirement: Deployer Pipeline Phase Integration

The deployer agent SHALL run as Phase 8 (after summarizer) in the Ultraworks pipeline.

The deployer phase SHALL be optional and only triggered when `deploy: true` is present in the task metadata.

#### Scenario: Deployer triggered after successful pipeline
- **WHEN** all pipeline phases (1 through 7) complete successfully
- **AND** the task metadata includes `deploy: true`
- **THEN** Sisyphus delegates to `s-deployer` as Phase 8

#### Scenario: Deployer skipped when not requested
- **WHEN** a pipeline task does not include `deploy: true` in its metadata
- **THEN** the deployer phase is skipped entirely
- **AND** the pipeline ends after the summarizer phase as before

#### Scenario: Deployer skipped on pipeline failure
- **WHEN** any pipeline phase (1 through 7) reports failure
- **AND** the task metadata includes `deploy: true`
- **THEN** the deployer phase is skipped
- **AND** the summarizer reports that deployment was blocked due to pipeline failure

### Requirement: Deployer Model Routing

The deployer agent SHALL use `anthropic/claude-sonnet-4-6` as primary model for reliable tool use and instruction following.

The deployer agent SHALL have a fallback chain covering at least 3 alternative providers.

#### Scenario: Model fallback on rate limit
- **WHEN** primary model `anthropic/claude-sonnet-4-6` is rate-limited
- **THEN** the system falls back to `openai/gpt-5.4`, then `google/gemini-3.1-pro-preview`, then remaining providers in order
