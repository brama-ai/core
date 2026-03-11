## 1. Deployment Contract

- [x] 1.1 Define which services are:
  - mandatory application services
  - optional platform add-ons
  - stateful dependencies that may run in-cluster or externally
- [x] 1.2 Define the shared runtime contract across Docker and Kubernetes:
  - env vars
  - secrets
  - public URL configuration
  - migrations
  - readiness / liveness / startup checks
- [x] 1.3 Define image tagging, compatibility matrix, and upgrade policy for core and agents

## 2. Docker Packaging

- [x] 2.1 Refactor the existing compose topology into an operator-facing Docker bundle:
  - clear bundle variants
  - minimal required files
  - explicit overrides
- [x] 2.2 Provide a documented bootstrap flow for hobby/self-hosted production:
  - secrets
  - first startup
  - migrations
  - health verification
- [x] 2.3 Define the Docker upgrade flow:
  - supported image pinning strategy
  - pre-upgrade backup checklist
  - migration command sequence
  - service restart order
  - post-upgrade verification
  - rollback to previous image tags
- [x] 2.4 Document backup, restore, upgrade, and rollback procedures for the Docker path

## 3. Kubernetes Packaging

- [x] 3.1 Choose the Kubernetes packaging format:
  - Helm umbrella chart preferred
  - raw manifests only as implementation detail if needed
- [x] 3.2 Define which stateful services are bundled versus expected as external managed services
- [x] 3.3 Implement Kubernetes jobs/hooks for bootstrap and migrations
- [x] 3.4 Add ingress, secret references, probes, scaling hints, and persistence defaults
- [x] 3.5 Document a production-grade sample `values.yaml`
- [x] 3.6 Define the Kubernetes upgrade flow:
  - chart versioning and app version mapping
  - `helm upgrade` preflight review
  - migration hook or job behavior
  - rollout verification and timeout expectations
  - rollback via `helm rollback`
  - handling of incompatible value changes and persistent data migrations

## 4. Service Hardening

- [ ] 4.1 Ensure every HTTP service exposes production-ready health and readiness behavior
- [ ] 4.2 Ensure long-running workers and schedulers have safe restart and shutdown behavior in both
  Docker and Kubernetes
- [ ] 4.3 Remove hidden Compose-only assumptions from config and service wiring

## 5. Documentation

- [x] 5.1 Split deployment docs into clearly separated Docker and Kubernetes guides
- [x] 5.2 Add an architecture/deployment matrix showing supported topologies and trade-offs
- [x] 5.3 Add operator runbooks for:
  - install
  - upgrade
  - backup / restore
  - troubleshooting
- [x] 5.4 Add explicit upgrade runbooks with example commands for Docker and Kubernetes operators
- [x] 5.4 Add English mirrors or folder-based bilingual docs for operator-facing deployment guides

## 6. Quality Checks

- [ ] 6.1 Validate both deployment capabilities with OpenSpec strict validation
- [ ] 6.2 Verify at least one end-to-end smoke path for Docker and one install validation path for
  Kubernetes packaging (Docker path verified; Kubernetes packaging is pending)
