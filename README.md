# Brama Agent Platform

[🇺🇦 Українська версія](README.ua.md)

> ⚠️ **Project Status: Early Alpha / Architecture Phase**  
> This project is currently in a documentation-first design phase using OpenSpec. While foundational infrastructure (Symfony 7, Docker, LiteLLM) is bootstrapped, core features like multi-tenancy, robust agent routing, and production readiness are actively being designed.  
> 🗺️ **[View our ROADMAP.md for detailed progress and plans.](ROADMAP.md)**

## Description

**Brama Agent Platform** is a modular platform for building, running, and governing production agent systems. The platform combines autonomous AI agents and classical microservices into a single operational surface so teams can ship agentic products without locking themselves into one language, framework, or runtime layer.

Whether you are building an internal agent platform, a workflow-heavy product, or a hybrid system that blends agents with traditional services, Brama provides the foundation to run and integrate those capabilities coherently.

Most importantly: **the platform does not limit your technology choices (Language & Framework Agnostic).** Agents and microservices can be written in any programming language (Python, TypeScript, Go, PHP, Rust, etc.) using any framework. Everything is unified via standardized protocols.

## Idea

The world is rapidly moving from traditional applications to ecosystems of autonomous agents that can solve tasks, collaborate, and operate inside real production systems. Brama is intended to become an open, production-grade platform for that transition: a place to create agents, connect them, run them, and govern them as one system.

**Key Concepts and Potential:**
- **"Bricks" Architecture:** Build products from independent components. Today, you can deploy a Knowledge Extractor (Wiki) or a News Digest, and tomorrow you can integrate a custom agent for analytics or business process automation.
- **Boundless Hybridization:** The platform organically combines AI agents, traditional backend services (Core Symfony App), frontend dashboards, and external APIs into a single monolithic user experience.
- **A2A-Native Orchestration:** Platform protocols allow agents not only to handle user requests but also to delegate tasks to each other through Agent-to-Agent communication.
- **Enterprise-ready Infrastructure:** With integrated solutions like **OpenClaw** (runtime and message processing environment) and **LiteLLM Gateway** (a single control pane for accessing and managing costs for any LLM globally — OpenRouter, Anthropic, OpenAI), the system is ready for heavy workloads.

Brama gives teams the tools to build the agent layer of modern software today, while keeping the path open toward a more platform-owned runtime over time.

## How to setup locally

We use Docker Compose for rapid deployment of the entire environment. Detailed instructions and default credentials can be found in [docs/setup/local-dev/en/local-dev.md](docs/setup/local-dev/en/local-dev.md).

**Quick Start:**

```bash
# 1. Clone the repository
git clone https://github.com/nmdimas/ai-community-platform.git
cd ai-community-platform

# 2. Configure secrets (one-time setup)
cp .env.local.example .env.local
# Make sure to edit .env.local — add your LLM key (e.g., OPENROUTER_API_KEY)
# and Telegram Bot token (TELEGRAM_BOT_TOKEN).

# 3. Bootstrap configuration (generates keys and distributes secrets)
make bootstrap

# 4. Build and start the platform's Docker stack
make setup
make up

# 5. Initialize the database for LiteLLM
make litellm-db-init

# 6. Run database migrations
make migrate

# 7. Run tests to verify the setup
make test
```

After a successful launch, the environment will be accessible at:
- **Platform:** `http://localhost/`
- **Core Admin:** `http://localhost/admin/login`
- **Wiki Agent:** `http://localhost/wiki`
- **OpenClaw UI:** `http://localhost:8082/`
- **LiteLLM API:** `http://localhost:4000/`

## How to setup in Kubernetes (Draft / WIP)

> **Draft:** This section and the deployment process are currently under active development (WIP).

Since the platform is designed for an enterprise approach, we are actively preparing Helm charts and configurations for seamless deployment in Kubernetes. In the future, this section will cover:
- Setting up Ingress controllers for traffic routing between agents and web surfaces.
- Secure secret management (External Secrets or Sealed Secrets).
- Configuring Persistent Volume Claims for data durability (Postgres, Redis, OpenSearch).
- CI/CD pipelines for automatic releases of new agent versions ("bricks") into the K8s cluster.

## Branding Note

The product brand is now **Brama Agent Platform** with the public domain `brama.dev`.
Some repository, package, and infrastructure identifiers still use the legacy
`ai-community-platform` naming and will be migrated separately from this documentation pass.
