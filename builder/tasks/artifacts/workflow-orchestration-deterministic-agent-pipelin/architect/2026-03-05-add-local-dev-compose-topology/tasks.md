## 1. Topology Definition

- [x] 1.1 Define `Traefik` entrypoints and service routing for `core`, `admin-stub`, and `openclaw-stub`.
- [x] 1.2 Document that the `admin` surface is a technical placeholder and does not establish a product web admin panel for MVP.

## 2. Local Bootstrap

- [x] 2.1 Add the initial `docker compose` file with `Traefik` and the three stub services.
- [x] 2.2 Add minimal hello world responses for each surface so routing can be verified without full application code.
- [x] 2.3 Add `Postgres`, `Redis`, `OpenSearch`, and `RabbitMQ` to the local stack with development-friendly defaults.

## 3. Core Service Direction

- [x] 3.1 Structure the `core` container so it can evolve into `PHP + Symfony 7 + Composer + Neuron AI`.
- [x] 3.2 Keep the first bootstrap small: no database dependency and no full framework initialization yet.

## 4. Verification

- [x] 4.1 Verify `core` on `http://localhost/`.
- [x] 4.2 Verify `admin-stub` on a dedicated local port through `Traefik`.
- [x] 4.3 Verify `openclaw-stub` on a dedicated local port through `Traefik`.
- [x] 4.4 Document startup commands and boundary assumptions.
- [x] 4.5 Verify that the infrastructure services start and expose their expected local ports.
