## 1. Agent Definition Files

- [x] 1.1 Create `.opencode/agents/deployer.md` — unified deployer agent definition with frontmatter (model, tools, permissions) and system prompt covering all four deployment strategies
- [x] 1.2 Create `.opencode/agents/s-deployer.md` — Ultraworks subagent wrapper that delegates to deployer with pipeline context
- [x] 1.3 Register `deployer` and `s-deployer` in the agent type enum used by the Task tool (update `.opencode/agents/` index if one exists)

## 2. Deployer Skill

- [x] 2.1 Create `.opencode/skills/deployer/SKILL.md` — skill file with deployment strategy workflows, safety gate checklist, SSH integration instructions, and health verification steps
- [x] 2.2 Register the skill in the shared skill index so agents can load it

## 3. Deployment Strategy Implementations

- [x] 3.1 Document `pr-only` strategy workflow: push branch, create PR via `gh pr create`, report PR URL in handoff
- [x] 3.2 Document `merge-and-deploy` strategy workflow: create PR, enable auto-merge via `gh pr merge --auto`, wait for CI deploy, verify health
- [x] 3.3 Document `direct-ssh` strategy workflow: SSH connect via MCP, `cd` to app path, `git pull`, `docker compose up -d --build`, verify health
- [x] 3.4 Document `helm-upgrade` strategy workflow: SSH connect, update image tags, `helm upgrade --install`, verify rollout status, verify health

## 4. Safety Gates

- [x] 4.1 Implement pre-deployment checklist in skill: verify all pipeline stages passed, verify `deploy: true` metadata, verify strategy is configured
- [x] 4.2 Implement dry-run mode: deployer shows planned actions without executing, reports what would happen in handoff
- [x] 4.3 Document rollback procedures for each strategy in the skill file

## 5. Pipeline Integration

- [x] 5.1 Update pipeline orchestration awareness so deployer runs as Phase 8 (after summarizer), only when explicitly requested
- [x] 5.2 Define deployer trigger conditions: `deploy: true` in task metadata or pipeline config, all previous stages passed

## 6. Documentation

- [x] 6.1 Create `docs/pipeline/en/deployer-agent.md` — developer-facing documentation for the deployer agent, strategies, configuration, and safety gates
- [x] 6.2 Create `docs/pipeline/ua/deployer-agent.md` — Ukrainian mirror
- [x] 6.3 Update pipeline overview documentation to include the deployer stage

## 7. Quality Checks

- [x] 7.1 Validate OpenSpec: `openspec validate add-deployer-pipeline-agent --strict`
- [x] 7.2 Verify agent definition files follow naming conventions from existing agents (compare with `s-summarizer.md`, `s-translater.md`)
- [x] 7.3 Verify skill file follows shared skill format (compare with existing skills in `.opencode/skills/`)
