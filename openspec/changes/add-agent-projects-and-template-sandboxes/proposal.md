# Change: Add agent projects with remote repositories and template sandboxes

## Why

The platform is moving from bundled agent source code toward independently managed agent repositories.
The previous external-agent work established the workspace contract, but the product still lacks a
first-class concept of a managed agent project: where code lives, how it is checked out, which
credentials it uses, which sandbox builds it, and how release/deploy should work.

Context note: the compact reasoning that led to this proposal is captured in
`brainstorm-context.md` in the same change directory.

The next step is to standardize one operational path instead of supporting multiple source models:

- managed agent code lives in a remote git repository
- the platform checks it out into `projects/<agent-name>/`
- each project declares its sandbox/build profile and deploy contract
- existing bundled agents move into that flow in explicit stages

The user already created three target repositories that can anchor the first migration wave:

- `https://github.com/nmdimas/a2a-hello-agent.git`
- `https://github.com/nmdimas/a2a-news-maker-agent.git`
- `https://github.com/nmdimas/a2a-wiki-agent.git`

These repositories also give us concrete stack baselines for the first sandbox templates.

## What Changes

- Add a new core capability: `agent-projects`
- Add a new core capability: `agent-sandbox-templates`
- Define `Agent Project` as the source-of-truth record for managed agent development:
  - remote repository URL and provider
  - private/self-hosted git host support
  - checkout path under `projects/`
  - sandbox configuration
  - release/deploy configuration
  - credential references
- Standardize one managed source model:
  - no new managed agents are created from `apps/`
  - managed projects always use a remote repository as source of truth
- Standardize sandbox selection per project:
  - built-in template sandbox
  - custom image / Dockerfile
  - compose-service-backed sandbox
- Introduce the first built-in sandbox templates from real agent stacks:
  - `php-symfony-agent` from `hello-agent`
  - `python-fastapi-agent` from `news-maker-agent`
  - `node-web-agent` from `wiki-agent`
- Define staged migration of existing bundled agents into the new flow:
  - Stage 1: foundation for `Agent Project` + repo-only workflow
  - Stage 2: extract `hello-agent` into `a2a-hello-agent` and validate the PHP template
  - Stage 3: extract `news-maker-agent` into `a2a-news-maker-agent` and validate the Python template
  - Stage 4: extract `wiki-agent` into `a2a-wiki-agent` and validate the Node template
- Prepare the future pipeline UI/release flow to target `Agent Project` records rather than raw files

## Impact

- Affected specs:
  - new capability `agent-projects`
  - new capability `agent-sandbox-templates`
  - related existing capability `external-agent-workspace`
  - related existing capability `external-agent-onboarding`
- Affected code:
  - `apps/brama-core` admin/domain model for agent projects
  - external-agent checkout and compose tooling
  - release/deploy orchestration hooks
  - docs and templates under `docs/guides/external-agents/`
- Breaking behavior / policy:
  - managed agent development becomes remote-repo-first only
  - bundled `apps/<agent-name>` agents become migration sources, not long-term managed origins
