## Context

Tasks 2.5 and 2.6 of the `workspace-deployment-model` change establish a clear ownership rule
for Dockerfiles and Compose files across the workspace, then enforce it by migrating any
remaining workspace-level application Dockerfiles into their owning projects.

The workspace (`brama`) is a monorepo shell that assembles multiple deployable projects
(`brama-core`, `brama-agents/*`, `brama-website`) using Docker Compose. The question of where
each project's `Dockerfile` lives has a direct impact on CI/CD portability, developer
discoverability, and the ability to build project images independently of the workspace.

### Stakeholders

- Developers who need to find and modify image build definitions
- CI/CD pipelines that build per-project images
- Operators who deploy images to k3s or other targets

## Goals / Non-Goals

### Goals

- Establish a documented rule: each deployable project owns its `Dockerfile` in its own
  repository root; Compose files remain in the workspace as the assembly layer
- Confirm that the core Dockerfile migration is complete (file lives in `brama-core/Dockerfile`,
  compose references point to `../brama-core` with `dockerfile: Dockerfile`)
- Clean up any empty leftover directories from the old layout (`docker/brama-core/`)
- Document the pattern so future projects follow it from the start

### Non-Goals

- Changing the `.devcontainer/Dockerfile` — this is a workspace-level tooling image, not a
  deployable project image
- Changing the `docker/slides/Dockerfile` — this is a workspace-level utility, not a deployable
  project
- Changing the `templates/agent/Dockerfile` — this is a scaffold template, not a live build
- Restructuring Compose file organization (that is covered by other tasks in this change)

## Decisions

### Decision 1: Project-owned Dockerfiles

Each deployable project SHALL own its `Dockerfile` at the project repository root.

**Rationale:** When the Dockerfile lives next to the code it builds, the project can be built
independently of the workspace. CI/CD pipelines, container registries, and deployment tools can
reference a single repository context without needing the full workspace tree.

**Current state (already conforming):**

| Project | Dockerfile location | Compose reference |
|---------|-------------------|-------------------|
| brama-core | `brama-core/Dockerfile` | `context: ../brama-core`, `dockerfile: Dockerfile` |
| hello-agent | `brama-agents/hello-agent/Dockerfile` | `context: ../brama-agents/hello-agent`, `dockerfile: Dockerfile` |
| knowledge-agent | `brama-agents/knowledge-agent/Dockerfile` | `context: ../brama-agents/knowledge-agent`, `dockerfile: Dockerfile` |
| news-maker-agent | `brama-agents/news-maker-agent/Dockerfile` | `context: ../brama-agents/news-maker-agent`, `dockerfile: Dockerfile` |
| wiki-agent | `brama-agents/wiki-agent/Dockerfile` | `context: ../brama-agents/wiki-agent`, `dockerfile: Dockerfile` |
| website | `brama-website/Dockerfile` | `context: ../brama-website`, `dockerfile: Dockerfile` |

**Exceptions (workspace-owned, not deployable projects):**

| File | Purpose | Owner |
|------|---------|-------|
| `.devcontainer/Dockerfile` | Developer tooling image | Workspace |
| `docker/slides/Dockerfile` | Presentation utility | Workspace |
| `templates/agent/Dockerfile` | Agent scaffold template | Workspace |

### Decision 2: Compose files remain in the workspace

All `compose*.yaml` files SHALL remain under `docker/` in the workspace repository. Compose
files are the assembly layer — they describe how independently-buildable projects are wired
together into a running topology. They reference project Dockerfiles via `build.context` and
`build.dockerfile` but do not own the image definitions.

**Rationale:** Compose topology is a workspace concern. A single project should not need to know
about the full topology to build its own image. Conversely, the workspace needs to know about
all projects to assemble the stack.

### Decision 3: Clean up empty `docker/brama-core/` directory

The empty `docker/brama-core/` directory is a leftover from the pre-migration layout. It SHALL
be removed as part of task 2.6 to avoid confusion.

**Alternatives considered:**

- *Keep it as a placeholder for future core-specific Docker assets* — Rejected. Any
  core-specific Docker assets should live in `brama-core/docker/` if needed, not in the
  workspace's `docker/` directory.

## Risks / Trade-offs

- **Risk:** Future contributors may place new project Dockerfiles in `docker/` out of habit.
  **Mitigation:** Document the rule in workspace README and in the agent template. The spec
  scenario makes the expectation testable.

- **Risk:** CI/CD pipelines may have hardcoded paths to `docker/<project>/Dockerfile`.
  **Mitigation:** Audit CI configs as part of task 2.6. Current compose files already reference
  the correct locations.

## Migration Plan

1. Verify `brama-core/Dockerfile` exists and is the active build definition (already done)
2. Verify all `compose*.yaml` files reference `context: ../brama-core` (already done)
3. Remove empty `docker/brama-core/` directory
4. Document the ownership rule in workspace deployment docs
5. Update the `local-dev-runtime` spec to codify the pattern

## Open Questions

None — the migration is already complete in practice. This change formalizes and documents it.
