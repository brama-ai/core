## Context

The platform already treats agents as modular runtime surfaces with a manifest endpoint, health
endpoint, and A2A interface. In practice, however, the current repository layout still treats
agents as if they are platform-owned subprojects under `apps/`. That slows down independent agent
release management and makes the eventual Docker and Kubernetes packaging story harder, because the
platform does not yet have a clean notion of "core repo + external agent repo".

This change proposes a repository and operator contract, not a marketplace or dynamic package
manager. The initial workflow is intentionally simple: the operator clones an agent repository into
the platform workspace and adds its compose fragment to the deployment.

## Goals / Non-Goals

- Goals:
  - decouple agent source ownership from the platform core repository
  - preserve the existing discovery and manifest contract
  - document a low-friction operator workflow based on `git clone`
  - make the future Docker and Kubernetes packaging model agent-repo aware
  - keep one consistent onboarding path for mixed-language agents

- Non-Goals:
  - building a remote marketplace that fetches repositories automatically
  - supporting arbitrary third-party agents without review
  - requiring Git submodules as the only supported workflow
  - replacing the existing admin registry/install UX

## Decisions

### 1. External agents use workspace checkout, not code copy

- **Decision**: the primary operator workflow is `git clone` into `projects/<agent-name>/`
- **Why**: it is explicit, reviewable, and works equally for hobby Docker setups and internal
  platform teams. It also avoids coupling the platform repo history to every agent release.
- **Alternatives considered**:
  - Git submodules as the default workflow — too operationally fragile for many users
  - vendoring agent code into `apps/` — defeats repository separation
  - auto-fetching remote repos from admin UI — too much trust and lifecycle complexity

### 2. Compose fragment is owned by the agent integration contract

- **Decision**: each external agent exposes a compose fragment or compose template describing its
  runtime service, labels, healthcheck, and dependencies
- **Why**: this keeps runtime ownership close to the agent and avoids undocumented manual edits in
  core compose files
- **Alternatives considered**:
  - a single monolithic platform-owned compose file for every agent — not scalable
  - pure documentation without a fragment contract — too error-prone for operators

### 3. Discovery remains contract-first and source-origin agnostic

- **Decision**: core discovers external agents using the same manifest and health rules as in-repo
  agents
- **Why**: source origin should not matter after the container is running; the runtime contract
  should stay uniform
- **Alternatives considered**:
  - special registration rules for external repositories — creates needless branching in core

### 4. One pilot agent should validate the pattern before a bulk split

- **Decision**: move one real agent first, prove the path, then use that repo as the template for
  the others
- **Why**: current agents use different stacks and storage patterns; the migration playbook should
  be validated before it becomes policy
- **Alternatives considered**:
  - moving every agent at once — higher risk and harder rollback

## Recommended Repository Pattern

- Platform repository owns:
  - shared infra compose files
  - admin/core services
  - discovery and lifecycle logic
  - platform documentation and compatibility checks
- External agent repository owns:
  - application source
  - Dockerfile
  - repo-local tests
  - manifest, health, and A2A handlers
  - compose fragment or compose template
  - migration commands and agent-specific env schema

Suggested operator workspace:

```text
ai-community-platform/
  compose.yaml
  compose.core.yaml
  compose.fragments/
    my-agent.yaml
  projects/
    hello-agent/
    knowledge-agent/
```

## Risks / Trade-offs

- More repositories means more release coordination
  - Mitigation: define compatibility matrix and required platform contract version
- External agent compose fragments may drift
  - Mitigation: validate labels, endpoint paths, and healthchecks through convention tests
- Some agents may still depend on in-repo assumptions
  - Mitigation: use a pilot migration and explicit decoupling checklist

## Migration Plan

1. Define the external workspace and compose contract
2. Pick one pilot agent and extract it to its own repository
3. Update docs and operator workflow
4. Make discovery and compatibility checks source-origin agnostic
5. Migrate the remaining agents in planned batches

## External References

- `Chatwoot` provides both container packaging and operator-facing self-hosted deployment guidance:
  https://developers.chatwoot.com/self-hosted/deployment/docker
- `Supabase` documents self-hosting as a curated Docker composition with explicit env and service
  contracts:
  https://supabase.com/docs/guides/self-hosting/docker
- `n8n` documents multiple server setup modes rather than forcing one packaging path:
  https://docs.n8n.io/hosting/installation/server-setups/
- `Open WebUI` separates simple install paths from more structured deployment guidance:
  https://docs.openwebui.com/getting-started/quick-start/
