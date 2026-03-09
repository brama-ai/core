AGENT_FILES := $(sort $(wildcard compose.agent-*.yaml))
OVERRIDE_FILE := $(firstword $(wildcard compose.override.yaml compose.override.yml))
OVERRIDE_COMPOSE := $(if $(OVERRIDE_FILE),-f $(OVERRIDE_FILE),)
COMPOSE_FILES := -f compose.yaml -f compose.core.yaml \
        $(addprefix -f ,$(AGENT_FILES)) \
        -f compose.langfuse.yaml -f compose.openclaw.yaml \
        -f compose.slides.yaml \
        $(OVERRIDE_COMPOSE)
COMPOSE ?= docker compose $(COMPOSE_FILES)
E2E_COMPOSE ?= docker compose $(COMPOSE_FILES) --profile e2e
E2E_CORE_DB ?= ai_community_platform_test
E2E_BASE_URL ?= http://localhost:18080

.PHONY: help bootstrap setup infra-setup core-setup knowledge-setup news-setup hello-setup dev-reporter-setup dev-agent-setup claw-setup \
	openclaw-frontdesk-sync \
        up up-observability down ps logs logs-traefik logs-core logs-litellm logs-openclaw logs-langfuse \
        agent-up agent-down \
        litellm-db-init e2e-db-init e2e-rabbitmq-init e2e-register-agents e2e-prepare e2e-cleanup \
        install migrate test analyse cs-check cs-fix e2e e2e-smoke \
        knowledge-install knowledge-migrate knowledge-test knowledge-analyse knowledge-cs-check knowledge-cs-fix \
        hello-install hello-test hello-analyse hello-cs-check hello-cs-fix \
        news-install news-migrate news-test news-analyse news-cs-check news-cs-fix \
        dev-reporter-install dev-reporter-migrate dev-reporter-test dev-reporter-analyse dev-reporter-cs-check dev-reporter-cs-fix \
        dev-agent-install dev-agent-migrate dev-agent-test dev-agent-analyse dev-agent-cs-check dev-agent-cs-fix \
        agent-discover conventions-test \
        sync-skills pipeline pipeline-batch

help:
	@printf '%s\n' \
		'make bootstrap             Configure secrets from .env.local (run once before setup)' \
		'make setup                Pull/build the current local stack dependencies (core + agents + claw + infra)' \
		'make openclaw-frontdesk-sync  Sync frontdesk policy files into .local/openclaw/state/workspace' \
		'make install              Install PHP dependencies via Composer inside the core container' \
		'make knowledge-install    Install PHP dependencies inside the knowledge-agent container' \
		'make news-install         Install Python dependencies inside the news-maker-agent container' \
		'make up                   Start the local stack in the background' \
		'make agent-up name=X      Start/update a single agent (e.g. make agent-up name=hello-agent)' \
		'make agent-down name=X    Stop a single agent (e.g. make agent-down name=hello-agent)' \
		'make down                 Stop the local stack' \
		'make ps                   Show running services' \
		'make logs                 Follow logs for all services' \
		'make logs-traefik         Follow Traefik logs' \
		'make logs-core            Follow core logs' \
		'make logs-litellm         Follow LiteLLM logs' \
		'make logs-openclaw        Follow OpenClaw gateway logs' \
		'make logs-langfuse        Follow Langfuse web/worker logs' \
		'make litellm-db-init      Ensure LiteLLM Postgres DB exists (fixes UI auth DB errors)' \
		'make e2e-prepare          Prepare full E2E stack (DBs + RabbitMQ vhost + migrations + agent registration)' \
		'make e2e-register-agents  Register and enable agents in core-e2e (called by e2e-prepare)' \
		'make e2e-cleanup          Stop all E2E containers' \
		'make test                 Run Codeception unit + functional suites for core (stack must be up)' \
		'make knowledge-test       Run Codeception suites for knowledge-agent (stack must be up)' \
		'make hello-install         Install PHP dependencies inside the hello-agent container' \
		'make hello-test            Run Codeception suites for hello-agent (stack must be up)' \
		'make hello-analyse         Run PHPStan static analysis for hello-agent (stack must be up)' \
		'make hello-cs-check        Check code style for hello-agent with PHP CS Fixer (stack must be up)' \
		'make hello-cs-fix          Fix code style for hello-agent with PHP CS Fixer (stack must be up)' \
		'make dev-reporter-install  Install PHP dependencies inside the dev-reporter-agent container' \
		'make dev-reporter-test     Run Codeception suites for dev-reporter-agent (stack must be up)' \
		'make dev-reporter-analyse  Run PHPStan static analysis for dev-reporter-agent (stack must be up)' \
		'make dev-reporter-cs-check Check code style for dev-reporter-agent with PHP CS Fixer (stack must be up)' \
		'make dev-reporter-cs-fix   Fix code style for dev-reporter-agent with PHP CS Fixer (stack must be up)' \
		'make dev-reporter-migrate  Run Doctrine migrations for dev-reporter-agent (stack must be up)' \
		'make news-test            Run pytest suites for news-maker-agent (stack must be up)' \
		'make news-analyse         Run ruff check for news-maker-agent (stack must be up)' \
		'make news-cs-check        Run ruff format check for news-maker-agent (stack must be up)' \
		'make news-cs-fix          Run ruff format fix for news-maker-agent (stack must be up)' \
		'make analyse              Run PHPStan static analysis for core (stack must be up)' \
		'make knowledge-analyse    Run PHPStan static analysis for knowledge-agent (stack must be up)' \
		'make cs-check             Check code style for core with PHP CS Fixer (stack must be up)' \
		'make knowledge-cs-check   Check code style for knowledge-agent with PHP CS Fixer (stack must be up)' \
		'make cs-fix               Fix code style for core with PHP CS Fixer (stack must be up)' \
		'make knowledge-cs-fix     Fix code style for knowledge-agent with PHP CS Fixer (stack must be up)' \
		'make migrate              Run Doctrine migrations for core (stack must be up)' \
		'make knowledge-migrate    Run Doctrine migrations for knowledge-agent (stack must be up)' \
		'make news-migrate         Run Alembic migrations for news-maker-agent (stack must be up)' \
		'make agent-discover       Run Traefik-based agent discovery and refresh registry' \
		'make conventions-test     Run Codecept.js agent-convention compliance tests (AGENT_URL required)' \
		'make e2e                  Run Codecept.js + Playwright E2E tests (full isolated stack)' \
		'make e2e-smoke            Run smoke-only E2E tests (API checks, no browser)'

bootstrap:
	@./scripts/bootstrap.sh

openclaw-frontdesk-sync:
	@./scripts/sync-openclaw-frontdesk.sh

setup: infra-setup core-setup knowledge-setup hello-setup news-setup dev-reporter-setup dev-agent-setup claw-setup slides-setup
	@echo "Local development dependencies are prepared."

infra-setup:
	$(COMPOSE) pull traefik postgres redis opensearch rabbitmq litellm

core-setup:
	$(COMPOSE) build core
	$(COMPOSE) run --rm core composer install
	$(COMPOSE) run --rm core ./vendor/bin/codecept build

knowledge-setup:
	$(COMPOSE) build knowledge-agent
	$(COMPOSE) run --rm knowledge-agent composer install
	$(COMPOSE) run --rm knowledge-agent ./vendor/bin/codecept build

hello-setup:
	$(COMPOSE) build hello-agent
	$(COMPOSE) run --rm hello-agent composer install
	$(COMPOSE) run --rm hello-agent ./vendor/bin/codecept build

dev-reporter-setup:
	$(COMPOSE) build dev-reporter-agent
	$(COMPOSE) run --rm dev-reporter-agent composer install
	$(COMPOSE) run --rm dev-reporter-agent ./vendor/bin/codecept build

dev-agent-setup:
	$(COMPOSE) build dev-agent
	$(COMPOSE) run --rm dev-agent composer install
	$(COMPOSE) run --rm dev-agent ./vendor/bin/codecept build

news-setup:
	$(COMPOSE) build news-maker-agent
	$(COMPOSE) run --rm news-maker-agent pip install -r requirements.txt

claw-setup:
	mkdir -p .local/openclaw/state .local/openclaw/e2e-state
	$(COMPOSE) pull openclaw-gateway openclaw-cli

slides-setup:
	$(COMPOSE) build slides

install:
	$(COMPOSE) run --rm core composer install

knowledge-install:
	$(COMPOSE) run --rm knowledge-agent composer install

hello-install:
	$(COMPOSE) run --rm hello-agent composer install

dev-reporter-install:
	$(COMPOSE) run --rm dev-reporter-agent composer install

dev-agent-install:
	$(COMPOSE) run --rm dev-agent composer install

news-install:
	$(COMPOSE) run --rm news-maker-agent pip install -r requirements.txt

up:
	$(COMPOSE) up --build -d

agent-up:
	$(COMPOSE) up --build -d $(name)

agent-down:
	$(COMPOSE) stop $(name)

up-observability:
	$(COMPOSE) up -d langfuse-web langfuse-worker

down:
	$(COMPOSE) down

ps:
	$(COMPOSE) ps

logs:
	$(COMPOSE) logs -f

logs-traefik:
	$(COMPOSE) logs -f traefik

logs-core:
	$(COMPOSE) logs -f core

logs-litellm:
	$(COMPOSE) logs -f litellm

logs-openclaw:
	$(COMPOSE) logs -f openclaw-gateway

logs-langfuse:
	$(COMPOSE) logs -f langfuse-web langfuse-worker

litellm-db-init:
	$(COMPOSE) up -d postgres
	@printf "SELECT 'CREATE DATABASE litellm' WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'litellm')\\gexec\n" | $(COMPOSE) exec -T postgres psql -U app -d ai_community_platform
	$(COMPOSE) up -d litellm

e2e-db-init:
	$(COMPOSE) up -d postgres
	@printf "SELECT 'CREATE DATABASE ai_community_platform_test' WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'ai_community_platform_test')\\gexec\n" | $(COMPOSE) exec -T postgres psql -U app -d postgres
	@printf "SELECT 'CREATE DATABASE knowledge_agent_test OWNER knowledge_agent' WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'knowledge_agent_test')\\gexec\n" | $(COMPOSE) exec -T postgres psql -U app -d postgres
	@printf "SELECT 'CREATE DATABASE news_maker_agent_test OWNER news_maker_agent' WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'news_maker_agent_test')\\gexec\n" | $(COMPOSE) exec -T postgres psql -U app -d postgres
	@printf "SELECT 'CREATE DATABASE dev_reporter_agent_test OWNER dev_reporter_agent' WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'dev_reporter_agent_test')\\gexec\n" | $(COMPOSE) exec -T postgres psql -U app -d postgres

e2e-rabbitmq-init:
	$(COMPOSE) up -d rabbitmq
	@$(COMPOSE) exec -T rabbitmq rabbitmqctl add_vhost test 2>/dev/null || true
	@$(COMPOSE) exec -T rabbitmq rabbitmqctl set_permissions -p test app ".*" ".*" ".*"

e2e-register-agents:
	@echo "Registering E2E agents in core-e2e..."
	@curl -sf -X POST http://localhost:18080/api/v1/internal/agents/register \
		-H "Content-Type: application/json" \
		-H "X-Platform-Internal-Token: dev-internal-token" \
		-d '{"name":"hello-agent","version":"1.0.0","description":"Simple hello-world reference agent","url":"http://hello-agent-e2e/api/v1/a2a","skills":[{"id":"hello.greet","name":"Hello Greet","description":"Greet a user by name"}],"skill_schemas":{"hello.greet":{"input_schema":{"type":"object","properties":{"name":{"type":"string"}}}}}}' \
		&& echo "  registered hello-agent" || echo "  FAILED hello-agent"
	@curl -sf -X POST http://localhost:18080/api/v1/internal/agents/register \
		-H "Content-Type: application/json" \
		-H "X-Platform-Internal-Token: dev-internal-token" \
		-d '{"name":"knowledge-agent","version":"1.0.0","description":"Knowledge base management and semantic search","url":"http://knowledge-agent-e2e/api/v1/knowledge/a2a","admin_url":"http://localhost:18083/admin/knowledge","skills":[{"id":"knowledge.search","name":"Knowledge Search","description":"Search the knowledge base"},{"id":"knowledge.upload","name":"Knowledge Upload","description":"Extract and store knowledge from messages"},{"id":"knowledge.store_message","name":"Knowledge Store Message","description":"Persist source messages with metadata"}]}' \
		&& echo "  registered knowledge-agent" || echo "  FAILED knowledge-agent"
	@curl -sf -X POST http://localhost:18080/api/v1/internal/agents/register \
		-H "Content-Type: application/json" \
		-H "X-Platform-Internal-Token: dev-internal-token" \
		-d '{"name":"news-maker-agent","version":"0.1.0","description":"AI-powered news curation and publishing","url":"http://news-maker-agent-e2e:8000/api/v1/a2a","admin_url":"http://localhost:18084/admin/sources","skills":[{"id":"news.publish","name":"News Publish","description":"Publish curated news content"},{"id":"news.curate","name":"News Curate","description":"Curate and summarize news articles"}]}' \
		&& echo "  registered news-maker-agent" || echo "  FAILED news-maker-agent"
	@curl -sf -X POST http://localhost:18080/api/v1/internal/agents/register \
		-H "Content-Type: application/json" \
		-H "X-Platform-Internal-Token: dev-internal-token" \
		-d '{"name":"dev-reporter-agent","version":"1.0.0","description":"Pipeline observability agent","url":"http://dev-reporter-agent-e2e/api/v1/a2a","admin_url":"http://localhost:18087/admin/pipeline","skills":[{"id":"devreporter.ingest","name":"Pipeline Ingest","description":"Ingest pipeline run reports"},{"id":"devreporter.status","name":"Pipeline Status","description":"Query pipeline run status"},{"id":"devreporter.notify","name":"Pipeline Notify","description":"Send notification messages"}]}' \
		&& echo "  registered dev-reporter-agent" || echo "  FAILED dev-reporter-agent"
	@$(E2E_COMPOSE) exec -T postgres psql -U app -d ai_community_platform_test -q \
		-c "UPDATE agent_registry SET enabled = true, installed_at = now() WHERE name IN ('hello-agent', 'knowledge-agent', 'news-maker-agent', 'dev-reporter-agent')"
	@echo "E2E agents registered and enabled."

e2e-prepare: e2e-db-init e2e-rabbitmq-init
	$(E2E_COMPOSE) up -d --build core-e2e knowledge-agent-e2e knowledge-worker-e2e news-maker-agent-e2e hello-agent-e2e dev-reporter-agent-e2e openclaw-gateway-e2e
	$(E2E_COMPOSE) exec -T core-e2e php bin/console doctrine:migrations:migrate --no-interaction
	$(E2E_COMPOSE) exec -T knowledge-agent-e2e php bin/console doctrine:migrations:migrate --no-interaction
	$(E2E_COMPOSE) exec -T dev-reporter-agent-e2e php bin/console doctrine:migrations:migrate --no-interaction
	$(E2E_COMPOSE) exec -T news-maker-agent-e2e alembic upgrade head
	@$(MAKE) e2e-register-agents

e2e-cleanup:
	$(E2E_COMPOSE) stop core-e2e knowledge-agent-e2e knowledge-worker-e2e news-maker-agent-e2e hello-agent-e2e dev-reporter-agent-e2e openclaw-gateway-e2e 2>/dev/null || true

migrate:
	$(COMPOSE) exec core php bin/console doctrine:migrations:migrate --no-interaction

knowledge-migrate:
	$(COMPOSE) exec knowledge-agent php bin/console doctrine:migrations:migrate --no-interaction

dev-reporter-migrate:
	$(COMPOSE) exec dev-reporter-agent php bin/console doctrine:migrations:migrate --no-interaction

dev-agent-migrate:
	$(COMPOSE) exec dev-agent php bin/console doctrine:migrations:migrate --no-interaction

news-migrate:
	$(COMPOSE) exec news-maker-agent alembic upgrade head

test:
	$(COMPOSE) exec core ./vendor/bin/codecept run

knowledge-test:
	$(COMPOSE) exec knowledge-agent ./vendor/bin/codecept run

hello-test:
	$(COMPOSE) exec hello-agent ./vendor/bin/codecept run

dev-reporter-test:
	$(COMPOSE) exec dev-reporter-agent ./vendor/bin/codecept run

dev-agent-test:
	$(COMPOSE) exec dev-agent ./vendor/bin/codecept run

news-test:
	$(COMPOSE) exec news-maker-agent python -m pytest tests/ -v

news-analyse:
	$(COMPOSE) exec news-maker-agent ruff check app/ tests/

news-cs-check:
	$(COMPOSE) exec news-maker-agent ruff format --check app/ tests/

news-cs-fix:
	$(COMPOSE) exec news-maker-agent ruff format app/ tests/

analyse:
	$(COMPOSE) exec core ./vendor/bin/phpstan analyse

hello-analyse:
	$(COMPOSE) exec hello-agent ./vendor/bin/phpstan analyse

dev-reporter-analyse:
	$(COMPOSE) exec dev-reporter-agent ./vendor/bin/phpstan analyse

dev-agent-analyse:
	$(COMPOSE) exec dev-agent ./vendor/bin/phpstan analyse

knowledge-analyse:
	$(COMPOSE) exec knowledge-agent ./vendor/bin/phpstan analyse

cs-check:
	$(COMPOSE) exec core ./vendor/bin/php-cs-fixer check --diff --allow-risky=yes

hello-cs-check:
	$(COMPOSE) exec hello-agent ./vendor/bin/php-cs-fixer check --diff --allow-risky=yes

dev-reporter-cs-check:
	$(COMPOSE) exec dev-reporter-agent ./vendor/bin/php-cs-fixer check --diff --allow-risky=yes

dev-agent-cs-check:
	$(COMPOSE) exec dev-agent ./vendor/bin/php-cs-fixer check --diff --allow-risky=yes

knowledge-cs-check:
	$(COMPOSE) exec knowledge-agent ./vendor/bin/php-cs-fixer check --diff --allow-risky=yes

cs-fix:
	$(COMPOSE) exec core ./vendor/bin/php-cs-fixer fix --allow-risky=yes

hello-cs-fix:
	$(COMPOSE) exec hello-agent ./vendor/bin/php-cs-fixer fix --allow-risky=yes

dev-reporter-cs-fix:
	$(COMPOSE) exec dev-reporter-agent ./vendor/bin/php-cs-fixer fix --allow-risky=yes

dev-agent-cs-fix:
	$(COMPOSE) exec dev-agent ./vendor/bin/php-cs-fixer fix --allow-risky=yes

knowledge-cs-fix:
	$(COMPOSE) exec knowledge-agent ./vendor/bin/php-cs-fixer fix --allow-risky=yes

agent-discover:
	$(COMPOSE) exec core php bin/console agent:discovery

logs-setup:
	$(COMPOSE) exec core php bin/console logs:index:setup

logs-cleanup:
	$(COMPOSE) exec core php bin/console logs:cleanup

conventions-test:
	cd tests/agent-conventions && npm install && AGENT_URL=$(AGENT_URL) npx codeceptjs run --steps

e2e: e2e-prepare
	cd tests/e2e && npm install && npx playwright install chromium --with-deps && \
		BASE_URL=$${BASE_URL:-$(E2E_BASE_URL)} \
		CORE_DB_NAME=$${CORE_DB_NAME:-$(E2E_CORE_DB)} \
		KNOWLEDGE_URL=$${KNOWLEDGE_URL:-http://localhost:18083} \
		NEWS_URL=$${NEWS_URL:-http://localhost:18084} \
		HELLO_URL=$${HELLO_URL:-http://localhost:18085} \
		OPENCLAW_URL=$${OPENCLAW_URL:-http://localhost:28789} \
		npx codeceptjs run --steps

e2e-smoke: e2e-prepare
	cd tests/e2e && npm install && \
		BASE_URL=$${BASE_URL:-$(E2E_BASE_URL)} \
		CORE_DB_NAME=$${CORE_DB_NAME:-$(E2E_CORE_DB)} \
		KNOWLEDGE_URL=$${KNOWLEDGE_URL:-http://localhost:18083} \
		NEWS_URL=$${NEWS_URL:-http://localhost:18084} \
		HELLO_URL=$${HELLO_URL:-http://localhost:18085} \
		OPENCLAW_URL=$${OPENCLAW_URL:-http://localhost:28789} \
		npx codeceptjs run --steps --grep @smoke

sync-skills:
	./scripts/sync-skills.sh

# ── Multi-Agent Pipeline ─────────────────────────────────────────────
pipeline:
	@test -n "$(TASK)" || (echo "Usage: make pipeline TASK=\"your task description\"" && exit 1)
	./scripts/pipeline.sh "$(TASK)"

pipeline-batch:
	@test -n "$(FILE)" || (echo "Usage: make pipeline-batch FILE=tasks.txt" && exit 1)
	./scripts/pipeline-batch.sh "$(FILE)"
