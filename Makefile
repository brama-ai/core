AGENT_FILES := $(sort $(wildcard compose.agent-*.yaml))
EXTERNAL_AGENT_FILES := $(sort $(wildcard compose.fragments/*.yaml))
OVERRIDE_FILE := $(firstword $(wildcard compose.override.yaml compose.override.yml))
OVERRIDE_COMPOSE := $(if $(OVERRIDE_FILE),-f $(OVERRIDE_FILE),)
COMPOSE_FILES := -f compose.yaml -f compose.core.yaml \
        $(addprefix -f ,$(AGENT_FILES)) \
        $(addprefix -f ,$(EXTERNAL_AGENT_FILES)) \
        -f compose.langfuse.yaml -f compose.openclaw.yaml \
        -f compose.slides.yaml \
        $(OVERRIDE_COMPOSE)
COMPOSE ?= docker compose $(COMPOSE_FILES)
E2E_COMPOSE ?= docker compose $(COMPOSE_FILES) --profile e2e
E2E_CORE_DB ?= ai_community_platform_test
E2E_BASE_URL ?= http://localhost:18080

# Devcontainer detection: run commands locally when inside a devcontainer
IS_DEVCONTAINER := $(or $(REMOTE_CONTAINERS),$(CODESPACES))
# run-in <service> <app-dir> <command...>  — run locally (cd apps/<dir>) or via docker compose exec
define run-in
$(if $(IS_DEVCONTAINER),cd apps/$(2) && $(3),$(COMPOSE) exec $(1) $(3))
endef

.PHONY: help bootstrap setup infra-setup core-setup knowledge-setup news-setup hello-setup dev-reporter-setup wiki-setup dev-agent-setup claw-setup \
	openclaw-frontdesk-sync \
        up up-observability down ps logs logs-traefik logs-core logs-litellm logs-openclaw logs-langfuse \
        agent-up agent-down \
        litellm-db-init e2e-db-init e2e-rabbitmq-init e2e-register-agents e2e-prepare e2e-cleanup \
        install migrate test analyse cs-check cs-fix e2e e2e-smoke \
        knowledge-install knowledge-migrate knowledge-test knowledge-analyse knowledge-cs-check knowledge-cs-fix \
        wiki-install wiki-test wiki-build \
        hello-install hello-test hello-analyse hello-cs-check hello-cs-fix \
        news-install news-migrate news-test news-analyse news-cs-check news-cs-fix \
        dev-reporter-install dev-reporter-migrate dev-reporter-test dev-reporter-analyse dev-reporter-cs-check dev-reporter-cs-fix \
        dev-agent-install dev-agent-migrate dev-agent-test dev-agent-analyse dev-agent-cs-check dev-agent-cs-fix \
        agent-discover conventions-test \
        external-agent-list external-agent-up external-agent-down external-agent-clone \
        sync-skills pipeline pipeline-batch monitor-builder monitor-ultraworks \
        monitor-ultraworks-launch monitor-ultraworks-attach monitor-ultraworks-menu

help:
	@printf '%s\n' \
		'make bootstrap             Configure secrets from .env.local (run once before setup)' \
		'make setup                Pull/build the current local stack dependencies (core + agents + claw + infra)' \
		'make openclaw-frontdesk-sync  Sync frontdesk policy files into .local/openclaw/state/workspace' \
		'make install              Install PHP dependencies via Composer inside the core container' \
		'make knowledge-install    Install PHP dependencies inside the knowledge-agent container' \
		'make news-install         Install Python dependencies inside the news-maker-agent container' \
		'make up                   Start the local stack in the background' \
		'make wiki-install         Install Node dependencies inside the wiki-agent container' \
		'make wiki-build           Run TypeScript build checks for wiki-agent (stack must be up)' \
		'make wiki-test            Run wiki-agent tests (stack must be up)' \
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
		'make external-agent-list  List detected external agent compose fragments in compose.fragments/' \
		'make external-agent-up name=X    Start/update a named external agent (e.g. make external-agent-up name=my-agent)' \
		'make external-agent-down name=X  Stop a named external agent (e.g. make external-agent-down name=my-agent)' \
		'make external-agent-clone repo=URL name=X  Clone an agent repo into projects/<name> (e.g. make external-agent-clone repo=https://github.com/org/my-agent name=my-agent)' \
		'make e2e                  Run Codecept.js + Playwright E2E tests (full isolated stack)' \
		'make e2e-smoke            Run smoke-only E2E tests (API checks, no browser)' \
		'make monitor-builder      Monitor builder pipeline (Claude Code)' \
		'make monitor-ultraworks   Monitor ultraworks pipeline (OpenCode/Sisyphus)' \
		'make monitor-ultraworks-launch TASK="desc"  Launch OpenCode in tmux' \
		'make monitor-ultraworks-attach  Attach to tmux session' \
		'make monitor-ultraworks-menu    Interactive menu'

bootstrap:
	@./scripts/bootstrap.sh

openclaw-frontdesk-sync:
	@./scripts/sync-openclaw-frontdesk.sh

setup: infra-setup core-setup knowledge-setup hello-setup news-setup dev-reporter-setup wiki-setup dev-agent-setup claw-setup slides-setup
	@echo "Local development dependencies are prepared."

infra-setup:
	$(if $(IS_DEVCONTAINER),@echo "Devcontainer: PostgreSQL and Redis already running locally",$(COMPOSE) pull traefik postgres redis opensearch rabbitmq litellm)

core-setup:
	$(if $(IS_DEVCONTAINER),cd apps/core && composer install && ./vendor/bin/codecept build,$(COMPOSE) build core && $(COMPOSE) run --rm core composer install && $(COMPOSE) run --rm core ./vendor/bin/codecept build)

knowledge-setup:
	$(if $(IS_DEVCONTAINER),cd apps/knowledge-agent && composer install && ./vendor/bin/codecept build,$(COMPOSE) build knowledge-agent && $(COMPOSE) run --rm knowledge-agent composer install && $(COMPOSE) run --rm knowledge-agent ./vendor/bin/codecept build)

hello-setup:
	$(if $(IS_DEVCONTAINER),cd apps/hello-agent && composer install && ./vendor/bin/codecept build,$(COMPOSE) build hello-agent && $(COMPOSE) run --rm hello-agent composer install && $(COMPOSE) run --rm hello-agent ./vendor/bin/codecept build)

dev-reporter-setup:
	$(if $(IS_DEVCONTAINER),cd apps/dev-reporter-agent && composer install && ./vendor/bin/codecept build,$(COMPOSE) build dev-reporter-agent && $(COMPOSE) run --rm dev-reporter-agent composer install && $(COMPOSE) run --rm dev-reporter-agent ./vendor/bin/codecept build)

dev-agent-setup:
	$(if $(IS_DEVCONTAINER),cd apps/dev-agent && composer install && ./vendor/bin/codecept build,$(COMPOSE) build dev-agent && $(COMPOSE) run --rm dev-agent composer install && $(COMPOSE) run --rm dev-agent ./vendor/bin/codecept build)

wiki-setup:
	$(if $(IS_DEVCONTAINER),cd apps/wiki-agent && npm install,$(COMPOSE) build wiki-agent && $(COMPOSE) run --rm wiki-agent npm install)

news-setup:
	$(if $(IS_DEVCONTAINER),cd apps/news-maker-agent && pip install -r requirements.txt,$(COMPOSE) build news-maker-agent && $(COMPOSE) run --rm news-maker-agent pip install -r requirements.txt)

claw-setup:
	mkdir -p .local/openclaw/state .local/openclaw/e2e-state
	$(if $(IS_DEVCONTAINER),@echo "Devcontainer: skipping OpenClaw Docker pull",$(COMPOSE) pull openclaw-gateway openclaw-cli)

slides-setup:
	$(if $(IS_DEVCONTAINER),@echo "Devcontainer: skipping slides Docker build",$(COMPOSE) build slides)

install:
	$(if $(IS_DEVCONTAINER),cd apps/core && composer install,$(COMPOSE) run --rm core composer install)

knowledge-install:
	$(if $(IS_DEVCONTAINER),cd apps/knowledge-agent && composer install,$(COMPOSE) run --rm knowledge-agent composer install)

hello-install:
	$(if $(IS_DEVCONTAINER),cd apps/hello-agent && composer install,$(COMPOSE) run --rm hello-agent composer install)

dev-reporter-install:
	$(if $(IS_DEVCONTAINER),cd apps/dev-reporter-agent && composer install,$(COMPOSE) run --rm dev-reporter-agent composer install)

dev-agent-install:
	$(if $(IS_DEVCONTAINER),cd apps/dev-agent && composer install,$(COMPOSE) run --rm dev-agent composer install)

news-install:
	$(if $(IS_DEVCONTAINER),cd apps/news-maker-agent && pip install -r requirements.txt,$(COMPOSE) run --rm news-maker-agent pip install -r requirements.txt)

wiki-install:
	$(if $(IS_DEVCONTAINER),cd apps/wiki-agent && npm install,$(COMPOSE) run --rm wiki-agent npm install)

up:
	$(if $(IS_DEVCONTAINER),@echo "Devcontainer: services (PostgreSQL, Redis) already running. Use 'php apps/core/bin/console server:start' to run Symfony.",$(COMPOSE) up --build -d)

agent-up:
	$(if $(IS_DEVCONTAINER),@echo "Devcontainer: start agents directly — e.g. cd apps/$(name) && php bin/console server:start",$(COMPOSE) up --build -d $(name))

agent-down:
	$(if $(IS_DEVCONTAINER),@echo "Devcontainer: stop agents directly",$(COMPOSE) stop $(name))

# ── External Agent Targets ───────────────────────────────────────────────────

external-agent-list:
	@./scripts/external-agent.sh list

external-agent-up:
	@test -n "$(name)" || (echo "Usage: make external-agent-up name=<agent-name>" && exit 1)
	@./scripts/external-agent.sh up "$(name)"

external-agent-down:
	@test -n "$(name)" || (echo "Usage: make external-agent-down name=<agent-name>" && exit 1)
	@./scripts/external-agent.sh down "$(name)"

external-agent-clone:
	@test -n "$(repo)" || (echo "Usage: make external-agent-clone repo=<git-url> name=<agent-name>" && exit 1)
	@test -n "$(name)" || (echo "Usage: make external-agent-clone repo=<git-url> name=<agent-name>" && exit 1)
	@./scripts/external-agent.sh clone "$(repo)" "$(name)"

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
	@$(E2E_COMPOSE) exec -T postgres psql -U app -d ai_community_platform_test -q \
		-c "INSERT INTO scheduled_jobs (agent_name, job_name, skill_id, payload, cron_expression, next_run_at, max_retries, retry_delay_seconds, timezone) VALUES ('hello-agent', 'daily-greeting', 'hello.greet', '{\"name\": \"Дмитро\"}', '0 9 * * *', now() + interval '1 day', 2, 120, 'Europe/Kyiv') ON CONFLICT (agent_name, job_name) DO NOTHING"
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
	$(call run-in,core,core,php bin/console doctrine:migrations:migrate --no-interaction)

knowledge-migrate:
	$(call run-in,knowledge-agent,knowledge-agent,php bin/console doctrine:migrations:migrate --no-interaction)

dev-reporter-migrate:
	$(call run-in,dev-reporter-agent,dev-reporter-agent,php bin/console doctrine:migrations:migrate --no-interaction)

dev-agent-migrate:
	$(call run-in,dev-agent,dev-agent,php bin/console doctrine:migrations:migrate --no-interaction)

news-migrate:
	$(call run-in,news-maker-agent,news-maker-agent,alembic upgrade head)

wiki-build:
	$(call run-in,wiki-agent,wiki-agent,npm run build)

wiki-test:
	$(call run-in,wiki-agent,wiki-agent,npm run test)

test:
	$(call run-in,core,core,./vendor/bin/codecept run)

knowledge-test:
	$(call run-in,knowledge-agent,knowledge-agent,./vendor/bin/codecept run)

hello-test:
	$(call run-in,hello-agent,hello-agent,./vendor/bin/codecept run)

dev-reporter-test:
	$(call run-in,dev-reporter-agent,dev-reporter-agent,./vendor/bin/codecept run)

dev-agent-test:
	$(call run-in,dev-agent,dev-agent,./vendor/bin/codecept run)

news-test:
	$(call run-in,news-maker-agent,news-maker-agent,python -m pytest tests/ -v)

news-analyse:
	$(call run-in,news-maker-agent,news-maker-agent,ruff check app/ tests/)

news-cs-check:
	$(call run-in,news-maker-agent,news-maker-agent,ruff format --check app/ tests/)

news-cs-fix:
	$(call run-in,news-maker-agent,news-maker-agent,ruff format app/ tests/)

analyse:
	$(call run-in,core,core,./vendor/bin/phpstan analyse)

hello-analyse:
	$(call run-in,hello-agent,hello-agent,./vendor/bin/phpstan analyse)

dev-reporter-analyse:
	$(call run-in,dev-reporter-agent,dev-reporter-agent,./vendor/bin/phpstan analyse)

dev-agent-analyse:
	$(call run-in,dev-agent,dev-agent,./vendor/bin/phpstan analyse)

knowledge-analyse:
	$(call run-in,knowledge-agent,knowledge-agent,./vendor/bin/phpstan analyse)

cs-check:
	$(call run-in,core,core,./vendor/bin/php-cs-fixer check --diff --allow-risky=yes)

hello-cs-check:
	$(call run-in,hello-agent,hello-agent,./vendor/bin/php-cs-fixer check --diff --allow-risky=yes)

dev-reporter-cs-check:
	$(call run-in,dev-reporter-agent,dev-reporter-agent,./vendor/bin/php-cs-fixer check --diff --allow-risky=yes)

dev-agent-cs-check:
	$(call run-in,dev-agent,dev-agent,./vendor/bin/php-cs-fixer check --diff --allow-risky=yes)

knowledge-cs-check:
	$(call run-in,knowledge-agent,knowledge-agent,./vendor/bin/php-cs-fixer check --diff --allow-risky=yes)

cs-fix:
	$(call run-in,core,core,./vendor/bin/php-cs-fixer fix --allow-risky=yes)

hello-cs-fix:
	$(call run-in,hello-agent,hello-agent,./vendor/bin/php-cs-fixer fix --allow-risky=yes)

dev-reporter-cs-fix:
	$(call run-in,dev-reporter-agent,dev-reporter-agent,./vendor/bin/php-cs-fixer fix --allow-risky=yes)

dev-agent-cs-fix:
	$(call run-in,dev-agent,dev-agent,./vendor/bin/php-cs-fixer fix --allow-risky=yes)

knowledge-cs-fix:
	$(call run-in,knowledge-agent,knowledge-agent,./vendor/bin/php-cs-fixer fix --allow-risky=yes)

agent-discover:
	$(call run-in,core,core,php bin/console agent:discovery)

logs-setup:
	$(call run-in,core,core,php bin/console logs:index:setup)

logs-cleanup:
	$(call run-in,core,core,php bin/console logs:cleanup)

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

# ── Monitoring Commands ─────────────────────────────────────
monitor-builder:
	@echo "=== Builder Pipeline Monitor (Claude Code) ==="
	@echo "Keys: [s] start, [k] kill, [f] retry, [+/-] priority, [q] quit"
	@./builder/monitor/pipeline-monitor.sh

monitor-ultraworks:
	@./builder/monitor/ultraworks-monitor.sh show
	@echo ""
	@echo "Commands:"
	@echo "  make monitor-ultraworks-launch  - Start OpenCode in tmux"
	@echo "  make monitor-ultraworks-attach  - Attach to running session"
	@echo "  make monitor-ultraworks-menu    - Interactive menu"

monitor-ultraworks-launch:
	@./builder/monitor/ultraworks-monitor.sh launch "$(TASK)"

monitor-ultraworks-attach:
	@tmux attach -t ultraworks 2>/dev/null || echo "No ultraworks session. Run: make monitor-ultraworks-launch"

monitor-ultraworks-menu:
	@./builder/monitor/ultraworks-monitor.sh menu

# ── Multi-Agent Builder Pipeline ─────────────────────────────────────
pipeline:
	@test -n "$(TASK)" || (echo "Usage: make pipeline TASK=\"your task description\"" && exit 1)
	./builder/pipeline.sh "$(TASK)"

pipeline-batch:
	@test -n "$(FILE)" || (echo "Usage: make pipeline-batch FILE=tasks.txt" && exit 1)
	./builder/pipeline-batch.sh "$(FILE)"

builder-setup:
	./builder/setup.sh
