# Operator Onboarding Guide — External Agents

This guide describes how to add, run, upgrade, and remove an externally maintained agent in a
self-hosted AI Community Platform deployment.

---

## Prerequisites

- Platform workspace cloned and running (`make up`)
- Docker Engine 24+ with Compose plugin v2
- Git

---

## 1. Clone the Agent Repository

```bash
# From the platform root
make external-agent-clone repo=https://github.com/your-org/my-agent.git name=my-agent
```

This clones the repository into `projects/my-agent/`.

Alternatively, clone manually:

```bash
mkdir -p projects
git clone https://github.com/your-org/my-agent.git projects/my-agent
```

---

## 2. Enable the Compose Fragment

Copy the agent-provided fragment into the operator-local fragments directory:

```bash
cp projects/my-agent/compose.fragment.yaml compose.fragments/my-agent.yaml
```

The platform `Makefile` auto-loads every `compose.fragments/*.yaml` file into the compose stack.

---

## 3. Configure Environment

If the agent requires environment variables beyond the defaults in its `compose.fragment.yaml`,
create `projects/my-agent/.env.local` or add an override in `compose.override.yaml`:

```yaml
# compose.override.yaml
services:
  my-agent:
    environment:
      MY_AGENT_API_KEY: your-secret-key
```

---

## 4. Run Setup and Start

```bash
# Start the agent (builds image if needed)
make external-agent-up name=my-agent

# Or start the full stack including the new agent
make up
```

---

## 5. Run Migrations (if applicable)

If the agent declares a `storage.postgres` block in its manifest, run its migrations:

```bash
docker compose -f compose.yaml -f compose.core.yaml \
  -f compose.fragments/my-agent.yaml \
  exec my-agent <migration-command>
```

The exact migration command is documented in the agent's own README.

---

## 6. Verify Health and Discovery

```bash
# Check health endpoint
curl -s http://localhost:<agent-port>/health

# Check manifest endpoint
curl -s http://localhost:<agent-port>/api/v1/manifest | jq .

# Trigger discovery in core
make agent-discover
```

The agent should appear in the core admin panel under **Agents → Marketplace** within 60 seconds
of the container starting.

---

## 7. Install and Enable in Admin

1. Open the core admin panel
2. Navigate to **Agents → Marketplace**
3. Click **Install** on the agent card
4. After installation completes, click **Enable**

The agent is now active and routing traffic.

---

## Upgrading an External Agent

```bash
# Pull the latest code
git -C projects/my-agent pull

# Rebuild and restart
make external-agent-up name=my-agent

# Run migrations if the agent has a database
docker compose -f compose.yaml -f compose.core.yaml \
  -f compose.fragments/my-agent.yaml \
  exec my-agent <migration-command>

# Verify health
curl -s http://localhost:<agent-port>/health

# Verify manifest compatibility
curl -s http://localhost:<agent-port>/api/v1/manifest | jq .name,.version
```

### Compatibility Check After Upgrade

After upgrading, trigger a discovery cycle to re-validate the agent manifest:

```bash
make agent-discover
```

If the agent no longer satisfies platform conventions, the admin panel shows a violation badge.
Check the violation details and either fix the agent or roll back.

### Rollback

```bash
# Roll back to the previous commit
git -C projects/my-agent checkout <previous-tag-or-commit>

# Rebuild and restart
make external-agent-up name=my-agent
```

---

## Removing an External Agent

```bash
# Stop the agent container
make external-agent-down name=my-agent

# Remove the copied compose fragment
rm -f compose.fragments/my-agent.yaml

# Optionally delete the checkout
rm -rf projects/my-agent
```

If the agent has persistent data (Postgres database, OpenSearch index, Redis keys), deprovision
it through the core admin panel first:

1. Open **Agents → Installed**
2. Click **Delete** on the agent card
3. Confirm the deprovision dialog

This removes the agent's database, index, and registry entry before you delete the checkout.

---

## Listing Detected External Agents

```bash
make external-agent-list
```

Output example:

```
External agent compose fragments:
  hello-agent                     projects/hello-agent
  my-agent                        projects/my-agent
```

---

## Related

- [External Agent Workspace](external-agent-workspace.md)
- [Migration Playbook](migration-playbook.md)
- [Agent Platform Conventions](../../../agent-requirements/conventions.md)
