# traefik-log-pipeline Specification

## Purpose
Collect Traefik reverse-proxy access logs and ship them into the shared
`platform_logs_*` OpenSearch indices so operators can search, filter, and
correlate HTTP traffic alongside application logs in the admin panel.

## ADDED Requirements

### Requirement: Fluent Bit Service Collects Traefik Access Logs
The platform SHALL run a Fluent Bit container that reads Traefik JSON access logs
from a shared volume and writes them into OpenSearch using the `platform_logs_*`
daily index pattern.

#### Scenario: Fluent Bit container starts with the platform stack
- **GIVEN** the platform compose stack is started
- **WHEN** `docker compose ps` is executed
- **THEN** a `fluent-bit` service SHALL be running and healthy
- **AND** it SHALL be connected to the `dev-edge` network

#### Scenario: Traefik access log entry arrives in OpenSearch
- **GIVEN** the platform stack is running
- **WHEN** an HTTP request passes through Traefik to any backend service
- **THEN** within 10 seconds an OpenSearch document SHALL appear in today's
  `platform_logs_YYYY_MM_DD` index
- **AND** the document SHALL have `app_name` = `traefik`
- **AND** the document SHALL have `channel` = `access`

### Requirement: Traefik Access Logs Written to Shared Volume
Traefik SHALL write JSON-formatted access logs to a file inside a Docker volume
that Fluent Bit can tail.

#### Scenario: Access log file is written by Traefik
- **GIVEN** the platform stack is running
- **WHEN** an HTTP request passes through Traefik
- **THEN** a JSON log line SHALL be appended to `/var/log/traefik/access.log`
  inside the Traefik container
- **AND** the log line SHALL contain `StartUTC`, `RequestMethod`, `RequestPath`,
  `OriginStatus`, `Duration`, and the kept request headers

### Requirement: Traefik Log Fields Map to Platform Schema
Fluent Bit SHALL transform Traefik JSON fields into the `platform_logs` index
schema so that existing queries, filters, and the admin UI work without changes.

#### Scenario: Field mapping produces correct platform_logs document
- **GIVEN** a Traefik JSON access log entry with standard fields
- **WHEN** Fluent Bit processes it
- **THEN** the output document SHALL contain:
  - `@timestamp` from Traefik's `StartUTC`
  - `request_method` from `RequestMethod`
  - `request_uri` from `RequestPath`
  - `client_ip` from `ClientAddr` with port stripped
  - `duration_ms` from `Duration` converted from nanoseconds to milliseconds
  - `status` from `OriginStatus`
  - `target_app` from `ServiceName` with `@docker` suffix stripped
  - `trace_id` from `request_X-Trace-Id` header (if present)
  - `request_id` from `request_X-Request-Id` header (if present)
  - `source_app` from `request_X-Service-Name` header (if present)
  - `message` composed as `"{method} {path} → {status} ({duration}ms)"`
  - `app_name` = `traefik`
  - `level_name` = `INFO`
  - `channel` = `access`

### Requirement: Admin App Filter Includes Traefik
The logs page app filter dropdown SHALL include `traefik` as a selectable option
so operators can filter to Traefik access logs specifically.

#### Scenario: Operator filters logs by app_name=traefik
- **GIVEN** the admin logs page is open
- **WHEN** the operator selects "traefik" from the App dropdown and clicks Search
- **THEN** only documents with `app_name` = `traefik` SHALL be shown
