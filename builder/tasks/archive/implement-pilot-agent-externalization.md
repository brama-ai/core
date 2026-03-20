<!-- batch: 20260311_104341 | status: pass | duration: 1069s | branch: pipeline/implement-pilot-agent-externalization -->
# Implement pilot agent externalization

Implement the approved OpenSpec change `refactor-agents-into-external-repositories` phase 2 using
one pilot agent.

## Goal

Prove that the external-agent repository model works in practice by migrating one real agent out of
the monorepo workflow and documenting the migration path.

## Scope

- Choose one current agent as the pilot candidate based on coupling and risk
- Extract or mirror that agent into the external-repository workflow defined by the platform
- Make the platform able to run the pilot agent from `projects/<agent-name>/`
- Document the migration playbook for future agents
- Keep the runtime contract stable:
  - service name
  - manifest endpoint
  - health endpoint
  - admin URL shape
  - A2A endpoint path

## OpenSpec References

- `openspec/changes/refactor-agents-into-external-repositories/proposal.md`
- `openspec/changes/refactor-agents-into-external-repositories/tasks.md`
- `openspec/changes/refactor-agents-into-external-repositories/design.md`

## Relevant Repo Context

- existing agent implementations under `apps/`
- `compose.agent-hello.yaml`
- `compose.agent-knowledge.yaml`
- `compose.agent-dev-reporter.yaml`
- `compose.agent-dev.yaml`
- `compose.agent-news-maker.yaml`
- `compose.agent-wiki.yaml`
- `docs/index.md`
- `docs/agents/`

## Acceptance Criteria

- One pilot agent is selected and the choice is justified in docs
- The platform can run the pilot from the external workspace contract rather than only from `apps/`
- A migration playbook exists for moving future agents to the same model
- Compatibility checks, manifest discovery, and runtime verification are documented and exercised
- The transition rules are explicit, especially around naming, URLs, and migrations

## Constraints

- Do not bulk-migrate all agents in one pass
- Do not introduce a second incompatible runtime contract for external agents
- Prefer the least coupled pilot agent first
- Keep docs and platform tooling aligned

## Validation

- Run relevant tests/checks for the chosen pilot workflow
- Run `openspec validate refactor-agents-into-external-repositories --strict`

