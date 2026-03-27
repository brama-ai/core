# Change: Add deployer pipeline agent for deployment automation

## Why

The pipeline (Foundry/Ultraworks) can produce validated code changes through its full agent chain (Planner → Architect → Coder → Validator → Tester → Auditor → Documenter → Summarizer), but has no "last mile" — no agent that takes completed, validated changes and deploys them to the target environment. Currently deployment relies on:

1. **GitHub Actions** (`deploy.yml`): auto-detect changed services → SSH to server → `docker compose up -d --build`
2. **Manual scripts**: `run_deploy.sh` with SSH/expect for K3s deployment
3. **MCP SSH agent**: interactive server access from devcontainer

None of these are integrated into the pipeline. A deployer agent closes this gap by providing a configurable, safe, auditable deployment step that the pipeline can invoke after all quality gates pass.

## What Changes

- **ADDED** `deployer` as a new pipeline stage (Phase 8, after summarizer)
- **ADDED** Four deployment strategies: `pr-only`, `merge-and-deploy`, `direct-ssh`, `helm-upgrade`
- **ADDED** Safety gates: explicit opt-in, dry-run default, rollback documentation, no force-push
- **ADDED** Agent definition files: `.opencode/agents/deployer.md` (unified) and `.opencode/agents/s-deployer.md` (Ultraworks subagent)
- **ADDED** SSH integration via MCP SSH agent configuration from `.devcontainer/.ssh-env`
- **ADDED** Health verification after deployment (curl health endpoint)

## Impact

- Affected specs: `pipeline-agents`
- Affected code:
  - `.opencode/agents/deployer.md` (new)
  - `.opencode/agents/s-deployer.md` (new)
  - `.opencode/skills/deployer/SKILL.md` (new)
  - `.opencode/pipeline/` (pipeline orchestration awareness)
- No database migrations required
- No API surface changes (deployer is a pipeline-internal agent, not a platform endpoint)
- No breaking changes to existing pipeline stages

## Risks

- **SSH key exposure**: Deployer needs SSH access to production servers. Mitigation: reuse existing `.ssh-env` configuration, never log credentials, require explicit opt-in.
- **Accidental production deployment**: Mitigation: dry-run by default, explicit `deploy: true` metadata required, all previous pipeline stages must pass.
- **Deployment failure without rollback**: Mitigation: deployer documents rollback steps before executing, verifies health after deployment, reports failure clearly in handoff.
- **Strategy misconfiguration**: Mitigation: validate strategy config before execution, fail fast on unknown strategy.
