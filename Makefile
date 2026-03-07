AGENT_FILES := $(sort $(wildcard compose.agent-*.yaml))
OVERRIDE_FILE := $(firstword $(wildcard compose.override.yaml compose.override.yml))
OVERRIDE_COMPOSE := $(if $(OVERRIDE_FILE),-f $(OVERRIDE_FILE),)
COMPOSE_FILES := -f compose.yaml -f compose.core.yaml \
        $(addprefix -f ,$(AGENT_FILES)) \
        -f compose.langfuse.yaml -f compose.openclaw.yaml \
        $(OVERRIDE_COMPOSE)
COMPOSE ?= docker compose $(COMPOSE_FILES)

.PHONY: help bootstrap setup infra-setup core-setup knowledge-setup news-setup hello-setup claw-setup \
        up up-observability down ps logs logs-traefik logs-core logs-litellm logs-openclaw logs-langfuse \
        agent-up agent-down \
        litellm-db-init \
        install migrate test analyse cs-check cs-fix e2e e2e-smoke \
        knowledge-install knowledge-migrate knowledge-test knowledge-analyse knowledge-cs-check knowledge-cs-fix \
        hello-install hello-test hello-analyse hello-cs-check hello-cs-fix \
        news-install news-migrate news-test news-analyse news-cs-check news-cs-fix agent-discover conventions-test \
        sync-skills

help:
	@printf '%s\n' \
		'make bootstrap             Configure secrets from .env.local (run once before setup)' \
		'make setup                Pull/build the current local stack dependencies (core + agents + claw + infra)' \
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
		'make test                 Run Codeception unit + functional suites for core (stack must be up)' \
		'make knowledge-test       Run Codeception suites for knowledge-agent (stack must be up)' \
		'make hello-install         Install PHP dependencies inside the hello-agent container' \
		'make hello-test            Run Codeception suites for hello-agent (stack must be up)' \
		'make hello-analyse         Run PHPStan static analysis for hello-agent (stack must be up)' \
		'make hello-cs-check        Check code style for hello-agent with PHP CS Fixer (stack must be up)' \
		'make hello-cs-fix          Fix code style for hello-agent with PHP CS Fixer (stack must be up)' \
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
		'make e2e                  Run Codecept.js + Playwright E2E tests (stack must be up)' \
		'make e2e-smoke            Run smoke-only E2E tests (API checks, no browser)'

bootstrap:
	@./scripts/bootstrap.sh

setup: infra-setup core-setup knowledge-setup hello-setup news-setup claw-setup
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

news-setup:
	$(COMPOSE) build news-maker-agent
	$(COMPOSE) run --rm news-maker-agent pip install -r requirements.txt

claw-setup:
	mkdir -p .local/openclaw/state
	$(COMPOSE) pull openclaw-gateway openclaw-cli

install:
	$(COMPOSE) run --rm core composer install

knowledge-install:
	$(COMPOSE) run --rm knowledge-agent composer install

hello-install:
	$(COMPOSE) run --rm hello-agent composer install

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

migrate:
	$(COMPOSE) exec core php bin/console doctrine:migrations:migrate --no-interaction

knowledge-migrate:
	$(COMPOSE) exec knowledge-agent php bin/console doctrine:migrations:migrate --no-interaction

news-migrate:
	$(COMPOSE) exec news-maker-agent alembic upgrade head

test:
	$(COMPOSE) exec core ./vendor/bin/codecept run

knowledge-test:
	$(COMPOSE) exec knowledge-agent ./vendor/bin/codecept run

hello-test:
	$(COMPOSE) exec hello-agent ./vendor/bin/codecept run

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

knowledge-analyse:
	$(COMPOSE) exec knowledge-agent ./vendor/bin/phpstan analyse

cs-check:
	$(COMPOSE) exec core ./vendor/bin/php-cs-fixer check --diff --allow-risky=yes

hello-cs-check:
	$(COMPOSE) exec hello-agent ./vendor/bin/php-cs-fixer check --diff --allow-risky=yes

knowledge-cs-check:
	$(COMPOSE) exec knowledge-agent ./vendor/bin/php-cs-fixer check --diff --allow-risky=yes

cs-fix:
	$(COMPOSE) exec core ./vendor/bin/php-cs-fixer fix --allow-risky=yes

hello-cs-fix:
	$(COMPOSE) exec hello-agent ./vendor/bin/php-cs-fixer fix --allow-risky=yes

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

e2e:
	cd tests/e2e && npm install && npx playwright install chromium --with-deps && BASE_URL=$(BASE_URL) npx codeceptjs run --steps

e2e-smoke:
	cd tests/e2e && npm install && BASE_URL=$(BASE_URL) npx codeceptjs run --steps --grep @smoke

sync-skills:
	./scripts/sync-skills.sh
