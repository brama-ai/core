# Implementation Tasks

## 1. Define Runtime Modes
- [ ] 1.1 Document Docker Compose as the primary single-node deployment mode
- [ ] 1.2 Document devcontainer as a development overlay built on the Compose stack
- [ ] 1.3 Document k3s as the cluster-oriented deployment mode
- [ ] 1.4 Ensure terminology is consistent across English and Ukrainian documentation

**Acceptance checks**
- `README.md` and `README.ua.md` describe the same three runtime modes
- Devcontainer is described as an overlay, not as an independent deployment topology
- Docker Compose is explicitly presented as the fastest path to run the platform on one machine

## 2. Define File Ownership and Layout
- [ ] 2.1 Document that `compose*.yaml`, `.devcontainer/`, `.env*.example`, `docker/`, and workspace scripts belong to the workspace repo
- [ ] 2.2 Document that product code and product docs remain in `brama-core/`
- [ ] 2.3 Define the target location for k3s deployment assets under `deploy/`
- [ ] 2.4 Define the target location for detailed deployment guides under `docs/deploy/`
- [ ] 2.5 Define that each deployable project owns its `Dockerfile` in the project directory, while
      Compose files continue to live in the workspace
- [ ] 2.6 Migrate the core Dockerfile out of the workspace-level `docker/` directory into the owning
      project and update all compose/build references

**Acceptance checks**
- The documented file layout matches the actual repository layout
- There is no ambiguity about whether a runtime file belongs in the workspace repo or `brama-core`
- Compose remains the assembly layer, not the owner of per-project image definitions

## 3. Define Verification Expectations
- [ ] 3.1 Document the minimum verification flow for Docker Compose
- [ ] 3.2 Document the minimum verification flow for devcontainer
- [ ] 3.3 Document the minimum verification flow for k3s
- [ ] 3.4 Require each deployment guide to include a "verify" section with concrete commands

**Acceptance checks**
- Each deployment mode has a short, executable verification sequence
- Verification steps include at least one runtime command and one expected success condition

## 4. Quality Checks
- [ ] 4.1 Review all deployment docs for contradictory wording
- [ ] 4.2 Confirm all README links resolve to real files
- [ ] 4.3 Validate proposal with OpenSpec tooling
