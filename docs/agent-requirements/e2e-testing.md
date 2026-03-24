# E2E Testing Guide

## Stack

| Tool | Purpose |
|------|---------|
| **Codecept.js** | Test runner + scenario DSL |
| **Playwright** | Browser engine (Chromium) |
| **REST helper** | API requests (no browser) |

All E2E tests live in `tests/e2e/`. They test the **full running stack** against
dedicated E2E containers backed by isolated data stores (`_test` suffix databases,
separate Redis DBs, separate RabbitMQ vhost).

---

## Isolation Architecture

E2E tests run against **duplicate containers** of every service, each connected to
isolated data stores. The same Docker image and code — only environment variables differ.

**Key principle:** Agent developers write ZERO infrastructure code to support E2E testing.
Isolation is purely via Docker Compose environment overrides using `profiles: [e2e]`.

### Resource Isolation

| Service | Prod resource | Test resource | Mechanism |
|---------|--------------|---------------|-----------|
| Postgres | `{agent_name}` | `{agent_name}_test` | Separate database |
| Redis | Even DB (0, 2, 4…) | Odd DB (1, 3, 5…) | Separate DB number |
| OpenSearch | `{index}` | `{index}_test` | Separate index |
| RabbitMQ | `/` (default vhost) | `test` | Separate vhost |

### Redis DB Assignments

| Service | Prod DB | Test DB |
|---------|---------|---------|
| Core | 0 | 1 |
| Knowledge Agent | 2 | 3 |
| (future agents) | 4, 6, 8… | 5, 7, 9… |

### E2E Container Ports (direct, no Traefik)

| Service | Prod port (Traefik) | E2E port (direct) |
|---------|--------------------|--------------------|
| Core | 80 | 18080 |
| Knowledge Agent | 8083 | 18083 |
| News-Maker Agent | 8084 | 18084 |
| Hello Agent | 8085 | 18085 |
| OpenClaw Gateway | 8082 / 18789 | 28789 |

### A2A Routing in E2E

```
E2E test → core-e2e → openclaw-gateway-e2e → agent-e2e containers
               │                                    │
               └── ai_community_platform_test DB    └── agent_test DBs
```

`make e2e-prepare` registers all agents in `core-e2e` with E2E container URLs
and enables them, so A2A message chains stay within the E2E graph.

---

## Directory Structure

```
tests/e2e/
├── codecept.conf.js          # Runner config, helpers, page objects
├── package.json
├── support/
│   ├── steps_file.js         # Shared custom steps (e.g. loginAsAdmin)
│   └── pages/
│       ├── LoginPage.js      # /admin/login interactions
│       └── AgentsPage.js     # /admin/agents interactions
└── tests/
    ├── smoke/                # Fast API checks, no browser
    │   ├── health_test.js    # Core /health endpoint
    │   └── traefik_test.js   # Traefik routing + registered services
    └── admin/
        └── agents_test.js    # Agents page UI + discovery + healthy badges
```

---

## Running Tests

```bash
# Full E2E suite (starts all E2E containers automatically)
make e2e

# Prepare only (DBs + RabbitMQ vhost + migrations + agent registration)
make e2e-prepare

# Verify the prepared stack before running tests
make e2e-env-check

# Stop E2E containers
make e2e-cleanup

# Specific tag only
cd tests/e2e && npx codeceptjs run --steps --grep @smoke
cd tests/e2e && npx codeceptjs run --steps --grep @admin

# Headed (see browser)
HEADLESS=false make e2e

# Custom base URL
BASE_URL=http://staging.example.com make e2e
```

`make e2e` and `make e2e-smoke` always run `make e2e-prepare` first, then `make e2e-env-check`.
This provisions all test databases, RabbitMQ `test` vhost, runs migrations
for all services, registers and enables agents in `core-e2e`, and starts
all E2E containers.

`make e2e-env-check` is a fail-fast gate. It verifies:
- Core `/health` and `/health/ready`
- agent `/health` endpoints for the E2E graph
- OpenClaw `/healthz`
- OpenClaw UI reachability

If any of these checks fail, the E2E run stops before Codecept.js or Playwright start.

**Important:** `make up` starts only prod services. E2E containers are started
only via `make e2e-prepare` (using `docker compose --profile e2e`).

---

## Writing a New Test

### 1. Choose the right folder

| Scenario type | Folder | Helper used |
|--------------|--------|-------------|
| API endpoint check (no UI) | `tests/smoke/` | REST |
| Admin panel UI interaction | `tests/admin/` | Playwright |
| Cross-service user journey | `tests/admin/` or new folder | Playwright |

### 2. File naming

Files must end in `_test.js`. Example: `knowledge_upload_test.js`.

### 3. Scenario anatomy

```js
Feature('Admin: My Feature');

// Before hook — runs before EACH scenario in this file
Before(async ({ I, loginPage }) => {
    await loginPage.loginAsAdmin();
});

Scenario('does something useful', async ({ I, agentsPage }) => {
    await agentsPage.open();
    I.see('Expected text');
    I.seeElement('.some-class');
}).tag('@admin');
```

### 4. API-only test (smoke)

```js
Feature('Smoke: My Endpoint');

Scenario('returns 200', async ({ I }) => {
    const res = await I.sendGetRequest('/api/v1/some-endpoint');
    I.assertEqual(res.status, 200);
    I.assertEqual(res.data.key, 'expected-value');
}).tag('@smoke');
```

### 5. Adding a Page Object

Create `support/pages/MyPage.js`:

```js
const { I } = inject();

module.exports = {
    url: '/admin/my-page',

    async open() {
        I.amOnPage(this.url);
        await I.waitForElement('table', 10);
    },

    seeItem(name) {
        I.see(name, 'table');
    },
};
```

Register it in `codecept.conf.js`:

```js
include: {
    myPage: './support/pages/MyPage.js',
},
```

Use in tests:

```js
Scenario('example', async ({ I, myPage }) => {
    await myPage.open();
    myPage.seeItem('expected-item');
});
```

---

## Tags

| Tag | Meaning |
|-----|---------|
| `@smoke` | Fast API checks, no browser, runs in CI |
| `@admin` | Admin panel UI tests |
| `@knowledge` | Knowledge agent flows |
| `@news` | News maker agent flows |

---

## What "All Agents Healthy" Means

The `tests/admin/agents_test.js` suite asserts the following after a discovery run:

1. `knowledge-agent` row is present in the table
2. `news-maker-agent` row is present in the table
3. Both have a `.badge-healthy` element (green badge)
4. Zero `.badge-error`, `.badge-degraded`, `.badge-unavailable` badges exist

For this to pass, each agent **must**:
- Be discoverable via Traefik (`*-agent@docker` service)
- Respond to `GET /api/v1/manifest` with valid JSON (name + version)
- Return HTTP 200 on `GET /health`

See [agent-requirements/conventions.md](agent-requirements/conventions.md) for full spec.
See [agent-requirements/agent-state-model.md](agent-state-model.md) for badge semantics and operator hint contract.

---

## Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `BASE_URL` | `http://localhost:18080` | Core E2E base URL |
| `CORE_DB_NAME` | `ai_community_platform_test` | Core test DB for SQL-backed helpers |
| `KNOWLEDGE_URL` | `http://localhost:18083` | Knowledge Agent E2E URL |
| `NEWS_URL` | `http://localhost:18084` | News-Maker Agent E2E URL |
| `HELLO_URL` | `http://localhost:18085` | Hello Agent E2E URL |
| `OPENCLAW_URL` | `http://localhost:28789` | OpenClaw Gateway E2E URL |
| `TRAEFIK_API` | `http://localhost:8080` | Traefik dashboard API |
| `ADMIN_PASSWORD` | `test-password` | Admin login password |
| `HEADLESS` | `true` | Set to `false` to see browser |
