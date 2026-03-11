# Pilot Agent Selection

## Decision

**`hello-agent`** is selected as the pilot external agent.

---

## Evaluation Criteria

Each bundled agent was evaluated against three criteria:

| Criterion | Weight | Description |
|-----------|--------|-------------|
| Coupling | High | Does the agent depend on platform internals beyond env vars? |
| Risk | High | What breaks if the migration fails? |
| Complexity | Medium | How many services, databases, and workers does the agent have? |

---

## Agent Inventory

| Agent | Stack | DB | Workers | Coupling | Risk | Complexity |
|-------|-------|----|---------|----------|------|------------|
| `hello-agent` | PHP/Symfony | None | None | Minimal | Low | Low |
| `knowledge-agent` | PHP/Symfony | Postgres + OpenSearch | Yes | Medium | High | High |
| `news-maker-agent` | Python/FastAPI | Postgres | None | Medium | Medium | Medium |
| `dev-reporter-agent` | PHP/Symfony | Postgres + OpenSearch | None | Medium | Medium | Medium |
| `wiki-agent` | TypeScript/Node.js | None | None | Low | Low | Low |
| `dev-agent` | PHP/Symfony | Postgres | None | Medium | Medium | Medium |

### Classification

| Agent | Classification | Reason |
|-------|---------------|--------|
| `hello-agent` | **Pilot candidate** | No database, no workers, minimal coupling, explicit reference agent |
| `wiki-agent` | Reference agent (may stay in-repo) | No database, but TypeScript stack differs from PHP agents |
| `knowledge-agent` | Production agent (migrate later) | Complex: Postgres + OpenSearch + worker, high risk |
| `news-maker-agent` | Production agent (migrate later) | Python stack, Postgres, medium risk |
| `dev-reporter-agent` | Production agent (migrate later) | Postgres + OpenSearch, medium risk |
| `dev-agent` | Production agent (migrate later) | Postgres, medium risk |

---

## Why hello-agent

1. **No database**: no migration commands, no Postgres provisioning, no data loss risk
2. **No background workers**: single container, simple lifecycle
3. **Minimal coupling**: only reads `LITELLM_BASE_URL`, `OPENSEARCH_URL`, and `LANGFUSE_*` from
   env — all standard platform env vars
4. **Explicit reference agent**: `hello-agent` is documented as the canonical example agent.
   Making it the pilot validates the pattern against the simplest possible case before applying
   it to more complex agents.
5. **Low blast radius**: if the migration fails, only the greeting skill is affected. No user
   data, no persistent state.

---

## Pilot Scope

The pilot does **not** move `hello-agent` out of `apps/` permanently. Instead, it:

1. Uses `hello-agent` as the validation target for the external workspace contract
2. Proves that the platform can run an agent from an operator-local checkout under
   `projects/hello-agent/` with a copied fragment in `compose.fragments/hello-agent.yaml`
3. Documents the migration path for future agents

The in-repo `compose.agent-hello.yaml` remains available. Operators can choose either path.

---

## Next Agents

After the pilot is validated, the recommended migration order is:

1. `wiki-agent` — no database, low risk
2. `news-maker-agent` — Python stack, medium complexity
3. `dev-reporter-agent` — PHP/Symfony, medium complexity
4. `dev-agent` — PHP/Symfony, medium complexity
5. `knowledge-agent` — highest complexity, migrate last

---

## Related

- [External Agent Workspace](external-agent-workspace.md)
- [Migration Playbook](migration-playbook.md)
- [Operator Onboarding Guide](operator-onboarding.md)
