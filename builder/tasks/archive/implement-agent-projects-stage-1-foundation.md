<!-- batch: 20260312_011612 | status: pass | duration: 1316s | branch: pipeline/implement-agent-projects-stage-1-foundation -->
<!-- priority: 5 -->
# Implement Agent Projects Stage 1 Foundation

Implement Stage 1 of the approved OpenSpec change `add-agent-projects-and-template-sandboxes`.

## Goal

Create the first platform foundation for repo-only managed agent projects so future pipeline,
release, and deploy flows can target a first-class `Agent Project` record instead of raw files and
ad hoc task metadata.

## Scope

- Introduce the `Agent Project` domain model in `apps/core`
- Define repository metadata for remote-repo-only managed agents
- Support private GitHub, GitLab.com, and self-hosted GitLab-like hosts at the config/model level
- Define credential reference fields without persisting raw secrets
- Define checkout/update contract into `projects/<project-slug>/`
- Define sandbox selection contract:
  - `template`
  - `custom_image`
  - `compose_service`
- Keep the current bundled agents operational during transition
- Do not yet implement full extraction of `hello-agent`, `news-maker-agent`, or `wiki-agent`

## OpenSpec References

- `openspec/changes/add-agent-projects-and-template-sandboxes/proposal.md`
- `openspec/changes/add-agent-projects-and-template-sandboxes/design.md`
- `openspec/changes/add-agent-projects-and-template-sandboxes/brainstorm-context.md`
- `openspec/changes/add-agent-projects-and-template-sandboxes/tasks.md`
- `openspec/changes/add-agent-projects-and-template-sandboxes/specs/agent-projects/spec.md`
- `openspec/changes/add-agent-projects-and-template-sandboxes/specs/agent-sandbox-templates/spec.md`
- `openspec/changes/refactor-agents-into-external-repositories/specs/external-agent-workspace/spec.md`
- `openspec/changes/refactor-agents-into-external-repositories/specs/external-agent-onboarding/spec.md`

## Relevant Repo Context

- `apps/core/`
- `docs/guides/external-agents/`
- `scripts/pipeline-run-task.sh`
- `scripts/pipeline-batch.sh`
- `compose.core.yaml`
- `compose.agent-news-maker.yaml`
- `compose.agent-wiki.yaml`
- `compose.agent-knowledge.yaml`

## Acceptance Criteria

- There is a concrete `Agent Project` model and persistence boundary in core
- The model captures repo-only managed agent metadata:
  - provider
  - host URL
  - remote URL
  - default branch
  - auth mode
  - credential ref
  - checkout path
- The model captures sandbox selection:
  - type
  - template id
  - custom image / Dockerfile reference
  - compose service reference
- The design keeps secrets out of persisted task payloads and visible logs
- The implementation does not break existing bundled agent discovery/runtime flows
- Docs are updated so the Stage 1 model is understandable to the next implementation stages

## Constraints

- Treat remote repositories as the only managed source-of-truth model
- `apps/<agent-name>` remains migration source only, not the long-term managed origin
- Do not implement a generic marketplace auto-cloner in this task
- Do not implement the full web kanban UI in this task
- Do not implement release/deploy execution yet beyond the model/contract boundaries needed for Stage 1

## Notes

The target extraction repositories for later stages are:

- `https://github.com/nmdimas/a2a-hello-agent.git`
- `https://github.com/nmdimas/a2a-news-maker-agent.git`
- `https://github.com/nmdimas/a2a-wiki-agent.git`

These repositories will later anchor the first three sandbox templates:

- `php-symfony-agent`
- `python-fastapi-agent`
- `node-web-agent`

## Validation

- Run relevant tests/checks for changed code
- Run `openspec validate add-agent-projects-and-template-sandboxes --strict`
