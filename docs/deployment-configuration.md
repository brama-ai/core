# Deployment Configuration

This document explains how to configure the AI Community Platform for different deployment modes (Docker Compose, Kubernetes, etc.).

## Overview

The platform uses environment variables to configure service connections and external dependencies. This allows the same application code to work across different deployment environments without hardcoded assumptions.

## Configuration Files

### `.env.deployment`

The main deployment configuration file that defines environment variables for service connections. Copy `.env.deployment.example` to `.env.deployment` and customize for your environment:

```bash
cp .env.deployment.example .env.deployment
```

### Environment Variable Structure

The configuration uses a hierarchical approach:

1. **Base service configuration**: Host, port, credentials
2. **Constructed URLs**: Built from base configuration
3. **Override capability**: Any variable can be overridden entirely

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

### Docker Compose (Default)

For Docker Compose deployments, the default values in `.env.deployment.example` work out of the box:

```bash
# Uses service names from docker-compose
POSTGRES_HOST=postgres
REDIS_HOST=redis
OPENSEARCH_HOST=opensearch
```

### Kubernetes

For Kubernetes deployments, override the host values to point to your services:

```bash
# Use Kubernetes service names or external endpoints
POSTGRES_HOST=postgresql-service
REDIS_HOST=redis-service
OPENSEARCH_HOST=opensearch-service.logging.svc.cluster.local

# Or use external managed services
DATABASE_URL=postgresql://user:pass@postgres.example.com:5432/ai_community_platform
REDIS_URL=redis://redis.example.com:6379
```

### External Services

For production deployments with managed services:

```bash
# Use managed PostgreSQL
DATABASE_URL=postgresql://user:pass@postgres.amazonaws.com:5432/ai_community_platform

# Use managed Redis
REDIS_URL=redis://redis.amazonaws.com:6379

# Use managed OpenSearch
OPENSEARCH_URL=https://search-domain.us-east-1.es.amazonaws.com
```

## Service Categories

### Core Infrastructure

- **PostgreSQL**: Primary database
- **Redis**: Caching and sessions
- **OpenSearch**: Search and logging
- **RabbitMQ**: Message queuing (knowledge-agent)

### Platform Services

- **Core Platform**: Main application service
- **LiteLLM**: LLM proxy service
- **Langfuse**: LLM observability (optional)

### Agent Services

Each agent can have its own database and configuration:

- **Knowledge Agent**: Separate PostgreSQL database
- **News Maker Agent**: Separate PostgreSQL database
- **Hello Agent**: Stateless, uses core services

## Health Checks

All services now expose enhanced health endpoints:

- `/health` - Basic liveness check
- `/health/live` - Kubernetes liveness probe
- `/health/ready` - Kubernetes readiness probe (checks dependencies)

## Migration from Hardcoded Configuration

If you have existing deployments with hardcoded service names, you can migrate gradually:

1. Create `.env.deployment` with your current values
2. Test that services still work
3. Gradually move to external services by updating environment variables
4. No code changes required

## Troubleshooting

### Service Connection Issues

Check that environment variables are correctly set:

```bash
# In a running container
env | grep -E "(DATABASE_URL|REDIS_URL|OPENSEARCH_URL)"
```

### Health Check Failures

Use the readiness endpoints to diagnose dependency issues:

```bash
curl http://core/health/ready
curl http://knowledge-agent/health/ready
```

### Configuration Validation

The platform validates configuration on startup and logs any issues. Check the application logs for configuration warnings.