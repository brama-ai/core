# Wiki Agent

A standalone external agent that provides knowledge base search and question answering capabilities using OpenSearch and PostgreSQL. Built in Node.js with TypeScript and Express.

## Prerequisites

- **Node.js 20** or higher
- **npm** (or yarn/pnpm)
- **PostgreSQL** schema provisioned
- **OpenSearch** instance

## Local Development Setup

To run this agent locally:

```bash
npm install
npm run dev
```

## Docker Standalone Run

You can build and run the agent locally using Docker:

```bash
docker build -t wiki-agent .
docker run -p 3000:3000 --env-file .env.local wiki-agent
```

## GHCR Image

We publish this agent's image to the GitHub Container Registry. You can pull the pre-built image instead of building it from source:

```bash
docker pull ghcr.io/nmdimas/a2a-wiki-agent:main
```

## Platform Integration

This agent comes with a `compose.fragment.yaml` which serves as its integration contract with the platform. To use this agent with the platform, include this compose fragment in your deployment stack.

## API Endpoints

| Endpoint | Description |
| -------- | ----------- |
| `/health` | Returns the health status of the agent |
| `/api/v1/manifest` | Returns the agent manifest describing its capabilities (wiki.search, wiki.answer) |
| `/api/v1/a2a` | The main A2A message exchange endpoint |
| `/wiki-admin` | Web interface for managing wiki pages |
| `/wiki` | Public web interface for the wiki |

## Environment Variables Configuration

See `.env.local.example` for all required environment variables. Copy `.env` to `.env.local` to override them for local development.
