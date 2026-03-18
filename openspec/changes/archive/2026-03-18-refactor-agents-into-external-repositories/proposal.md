# Change: Refactor agents into external repositories and define onboarding contract

## Why

The repository currently keeps platform core and all agent implementations under one source tree.
That is workable for the MVP, but it creates two growing problems:

- the platform repository becomes the delivery bottleneck for agents implemented in different
  stacks and release cadences
- operators do not yet have a clear, documented way to connect an externally maintained agent to
  the platform without editing core-owned files ad hoc

The platform already trends toward independent agent lifecycle through manifest-based discovery,
install/enable flows, and agent conventions. Moving agents toward external repositories is the next
step in making the platform modular in practice, not only in architecture diagrams.

## What Changes

- Define `external agent workspace` as a first-class platform capability
- Move currently bundled agents toward separate repositories owned by their own release lifecycle
- Standardize a repository checkout convention for self-hosted operators:
  - clone agent repositories into `projects/<agent-name>/`
  - keep the platform repo as the owner of shared infrastructure and operator tooling
- Standardize how an external agent plugs into the platform runtime:
  - agent repository provides its own Docker build context and compose fragment template
  - platform operator adds the fragment to the compose stack without copying agent source into `apps/`
  - agent remains discoverable through the existing manifest and health conventions
- Add developer and operator documentation for:
  - creating a new external agent repository
  - cloning an agent into a running platform workspace
  - wiring compose, migrations, health checks, and discovery
  - upgrading or removing an external agent checkout
- Define the boundary between this change and existing lifecycle work:
  - registry / marketplace remains core-owned
  - this change governs source layout, packaging, and onboarding, not admin install state

## Impact

- Affected specs:
  - new capability `external-agent-workspace`
  - new capability `external-agent-onboarding`
- Affected code and docs:
  - top-level compose loading strategy
  - `Makefile` helper targets for external agent workflows
  - agent templates / examples
  - `docs/agent-requirements/`
  - operator deployment and onboarding guides under `docs/`
- Related active changes:
  - aligns with `refactor-agent-discovery`
  - aligns with `add-agent-marketplace-and-deprovision`
  - should not duplicate registry, install, or enable semantics already owned by those changes
- Breaking considerations:
  - platform contributors can no longer assume every production agent lives under `apps/`
  - local development and CI must tolerate mixed source origins: in-repo core plus external agent
    checkouts
