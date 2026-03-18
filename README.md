# AI Community Platform

[🇺🇦 Українська версія](README.ua.md)

## Description

**AI Community Platform** is an innovative architectural ecosystem for building scalable **Agentic Solutions**. The platform offers a universal, modular approach where autonomous AI agents and classical microservices act as flexible "building blocks" (bricks) to construct systems of any complexity.

Whether you are building a platform to orchestrate complex logic for dozens of agents, an advanced web application with a powerful API, or a comprehensive hybrid solution — our platform provides a reliable foundation to run and seamlessly integrate them.

Most importantly: **the platform does not limit your technology choices (Language & Framework Agnostic).** Agents and microservices can be written in any programming language (Python, TypeScript, Go, PHP, Rust, etc.) using any framework. Everything is unified via standardized protocols.

## Idea

The world is rapidly moving from traditional applications to ecosystems of autonomous agents capable of independently solving tasks, communicating with each other, and interacting with users. Our global ambition is to provide developers, startups, and businesses with a unified, powerful, and fully open standard for merging classical development with AI.

**Key Concepts and Potential:**
- **"Bricks" Architecture:** Build products from independent components. Today, you can deploy a Knowledge Extractor (Wiki) or a News Digest, and tomorrow you can integrate a custom agent for analytics or business process automation.
- **Boundless Hybridization:** The platform organically combines AI agents, traditional backend services (Core Symfony App), frontend dashboards, and external APIs into a single monolithic user experience.
- **Agent Orchestration (a2a):** Platform protocols allow agents not only to listen to user queries but also to delegate tasks to each other (Agent-to-Agent communication).
- **Enterprise-ready Infrastructure:** With integrated solutions like **OpenClaw** (runtime and message processing environment) and **LiteLLM Gateway** (a single control pane for accessing and managing costs for any LLM globally — OpenRouter, Anthropic, OpenAI), the system is ready for heavy workloads.

The platform gives you the tools to create the digital employees of the future, today.

## How to setup locally

We use Docker Compose for rapid deployment of the entire environment. Detailed instructions and default credentials can be found in [docs/local-dev.md](docs/local-dev.md).

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
