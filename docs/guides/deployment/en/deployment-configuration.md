# Deployment Configuration

## Overview

This document explains how to configure the AI Community Platform for different deployment modes
(Docker Compose, Kubernetes, and external managed services).

## Configuration Files

### `.env.deployment`

The main deployment configuration file defines environment variables for service connections. Copy
`.env.deployment.example` to `.env.deployment` and customize it for your environment:

```bash
cp .env.deployment.example .env.deployment
```

### Environment Variable Structure

The configuration uses a layered approach:

1. Base service configuration: host, port, credentials
2. Constructed URLs: built from base configuration
3. Override capability: any variable can be overridden entirely

Example:

```bash
# Base configuration
POSTGRES_HOST=postgres
POSTGRES_PORT=5432
POSTGRES_USER=app
POSTGRES_PASSWORD=app

# Constructed URL (can be overridden)
DATABASE_URL=postgresql://${POSTGRES_USER}:${POSTGRES_PASSWORD}@${POSTGRES_HOST}:${POSTGRES_PORT}/ai_community_platform
```

## Deployment Modes

### Docker Compose

For Docker Compose deployments, the defaults in `.env.deployment.example` work out of the box:

```bash
POSTGRES_HOST=postgres
REDIS_HOST=redis
OPENSEARCH_HOST=opensearch
```

### Kubernetes

For Kubernetes deployments, override host values to point to service DNS names or external endpoints:

```bash
POSTGRES_HOST=postgresql-service
REDIS_HOST=redis-service
OPENSEARCH_HOST=opensearch-service.logging.svc.cluster.local

DATABASE_URL=postgresql://user:pass@postgres.example.com:5432/ai_community_platform
REDIS_URL=redis://redis.example.com:6379
```

### External Managed Services

For production deployments with managed infrastructure:

```bash
DATABASE_URL=postgresql://user:pass@postgres.amazonaws.com:5432/ai_community_platform
REDIS_URL=redis://redis.amazonaws.com:6379
OPENSEARCH_URL=https://search-domain.us-east-1.es.amazonaws.com
```

## Service Categories

### Core Infrastructure

- PostgreSQL: primary database
- Redis: caching and sessions
- OpenSearch: search and logging
- RabbitMQ: message queuing for knowledge workflows

### Platform Services

- Core platform: main application service
- LiteLLM: LLM proxy service
- Langfuse: LLM observability (optional)

### Agent Services

Each agent can have its own database and configuration:

- Knowledge Agent: separate PostgreSQL database
- News Maker Agent: separate PostgreSQL database
- Hello Agent: stateless, uses shared core services

## Health Checks

All services expose enhanced health endpoints:

- `/health` — basic liveness check
- `/health/live` — Kubernetes liveness probe
- `/health/ready` — Kubernetes readiness probe with dependency checks

## Migration from Hardcoded Configuration

If you have an older deployment with hardcoded service names:

1. Create `.env.deployment` with your current values
2. Verify that services still work
3. Move to external services gradually by updating environment variables
4. No code changes should be required

## Troubleshooting

### Service Connection Issues

Check that runtime variables are set as expected:

```bash
env | grep -E "(DATABASE_URL|REDIS_URL|OPENSEARCH_URL)"
```

### Health Check Failures

Use readiness endpoints to diagnose dependency issues:

```bash
curl http://brama-core/health/ready
curl http://knowledge-agent/health/ready
```

### Configuration Validation

The platform validates configuration on startup and logs issues as warnings or boot errors.
