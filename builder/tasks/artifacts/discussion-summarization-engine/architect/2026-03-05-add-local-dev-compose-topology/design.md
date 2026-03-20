## Context

The repository is still documentation-first and does not yet have an implementation runtime. At the same time, the team wants to start validating a local deployment shape for the future `core-platform` and a bounded `OpenClaw` runtime candidate.

Current repository constraints that this design must preserve:

- MVP is defined as having no separate web admin panel yet.
- `OpenClaw` is allowed only as a runtime for `core-agent`, not as `core-platform`.
- The first implementation should remain small and avoid overbuilding.

## Goals / Non-Goals

- Goals:
  - Provide a simple local development topology that can be started with one command.
  - Put `Traefik` in front of every public surface so routing behavior is explicit from the beginning.
  - Reserve a clean path for a future `PHP + Symfony 7 + Composer + Neuron AI` core service.
  - Include the baseline backing services that the platform expects to use locally.
  - Validate service isolation before real platform logic is added.
- Non-Goals:
  - Building a full Symfony application in this change.
  - Introducing a production-ready deployment model.
  - Approving a full web admin product surface for MVP.
  - Letting `OpenClaw` own platform data, gateway, or permissions.

## Proposed Topology

### Public Entry Layer

`Traefik` is the only component that binds host ports. The initial local entrypoints are:

- `web` on host port `80` for the `core` surface
- `admin` on host port `8081` for the `admin-stub` surface
- `openclaw` on host port `8082` for the `openclaw-stub` surface

This keeps the user-facing topology simple while preserving explicit port separation where needed.

### Routed Services

- `core`
  - Initial state: hello world HTTP response
  - Future state: `PHP + Symfony 7 + Composer + Neuron AI`
  - Purpose: own platform-facing HTTP/API surface and future platform bootstrap logic
- `admin-stub`
  - Initial state: hello world HTTP response
  - Purpose: routing and isolation placeholder only
  - Constraint: must be documented as non-product and optional
- `openclaw-stub`
  - Initial state: hello world HTTP response
  - Future state: replaceable `OpenClaw` runtime container
  - Purpose: validate a separate runtime surface without changing platform ownership

All routed services should stay on an internal Docker network and expose only container-local ports to `Traefik`.

### Backing Infrastructure

The local stack should also include the baseline infrastructure that the platform will depend on:

- `postgres`
  - Purpose: primary relational storage
  - Local port: `5432`
- `redis`
  - Purpose: cache, ephemeral state, and lightweight coordination
  - Local port: `6379`
- `opensearch`
  - Purpose: search indexing and search-oriented development work
  - Local port: `9200`
- `rabbitmq`
  - Purpose: queueing and async integration bootstrap
  - Local ports: `5672` for AMQP and `15672` for the management UI

These services are infrastructure dependencies for the local environment, not public application surfaces, so they should use direct local port exposure instead of `Traefik` routing.

## Boundary Rules

- `core` remains the owner of platform contracts, storage ownership, permissions, and product APIs.
- `OpenClaw` remains an isolated runtime candidate for orchestration behavior only.
- The `admin-stub` exists only to prove routing and separation; it does not change the documented MVP rule of no approved web admin panel.

## Bootstrap Notes

- The first `core` container should use a layout that can later host a Symfony app cleanly.
- A minimal `public/index.php` response is sufficient for the first bootstrap.
- The first compose stack may include backing services, but `core` does not need to depend on them yet.
- Stub responses should clearly identify which surface answered the request.

## Validation

After implementation, the stack should be verifiable with:

- `http://localhost/` for `core`
- `http://localhost:8081/` for `admin-stub`
- `http://localhost:8082/` for `openclaw-stub`
- `localhost:5432` for `postgres`
- `localhost:6379` for `redis`
- `http://localhost:9200/` for `opensearch`
- `localhost:5672` and `http://localhost:15672/` for `rabbitmq`

The response bodies and running container state should be sufficient to confirm that routed services and local infrastructure are available as expected.
