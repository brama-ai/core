# Hello Agent

A standalone external agent that provides basic greetings and health reports. It is built in PHP 8.3 using Symfony. This agent connects to the AI Community Platform as an external capability provider.

## Prerequisites

- **PHP 8.3** or higher
- **Composer**

## Local Development Setup

To run this agent locally without Docker:

```bash
composer install
php -S localhost:8080 -t public
```

## Docker Standalone Run

You can build and run the agent locally using Docker:

```bash
docker build -t hello-agent .
docker run -p 8080:80 hello-agent
```

## GHCR Image

We publish this agent's image to the GitHub Container Registry. You can pull the pre-built image instead of building it from source:

```bash
docker pull ghcr.io/nmdimas/a2a-hello-agent:main
```

## Platform Integration

This agent comes with a `compose.fragment.yaml` which serves as its integration contract with the platform. To use this agent with the platform, simply include this compose fragment in your deployment stack.

## API Endpoints

| Endpoint | Description |
| -------- | ----------- |
| `/health` | Returns the health status of the agent |
| `/api/v1/manifest` | Returns the agent manifest describing its capabilities |
| `/api/v1/a2a` | The main A2A message exchange endpoint |

## Environment Variables Configuration

See `.env.local.example` for all required environment variables. Copy `.env` to `.env.local` to override them for local development.
