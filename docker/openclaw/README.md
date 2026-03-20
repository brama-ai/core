# OpenClaw (Docker)

Local config and env files for the OpenClaw services running inside the root Docker Compose stack.

For the full setup guide (from scratch), see [docs/local-dev.md](../../docs/local-dev.md#openclaw-setup).

## Files

- `.env` — gateway token + Telegram bot token
- `../../.local/openclaw/state/openclaw.json` — main OpenClaw config (gitignored)
- `config-template.json` — example configuration with platform integration settings

## Quick Reference

```bash
# Start
docker compose up -d openclaw-gateway openclaw-cli

# Logs
docker compose logs -f openclaw-gateway

# Onboard wizard
docker compose exec openclaw-cli openclaw onboard

# Set a config value
docker compose exec openclaw-cli openclaw config set some.path someValue

# Restart after config changes
docker compose restart openclaw-gateway

# Set a config value
docker compose exec openclaw-cli openclaw config set some.path someValue

# Restart after config changes
docker compose restart openclaw-gateway

# Approve Telegram pairing
docker compose exec openclaw-cli openclaw pairing approve telegram <CODE>
```

## Agent Discovery Configuration

OpenClaw can automatically discover and use platform agents as tools through the discovery API.

### Discovery Polling

Configure OpenClaw to poll the platform's discovery endpoint:

```bash
# Set discovery endpoint URL
docker compose exec openclaw-cli openclaw config set discovery.endpoint "http://core:8080/api/v1/a2a/discovery"

# Set authorization token (must match OPENCLAW_GATEWAY_TOKEN in platform)
docker compose exec openclaw-cli openclaw config set discovery.token "your-gateway-token-here"

# Set poll interval (default: 30 seconds)
docker compose exec openclaw-cli openclaw config set discovery.poll_interval 30

# Enable discovery polling
docker compose exec openclaw-cli openclaw config set discovery.enabled true

# Restart to apply changes
docker compose restart openclaw-gateway
```

### A2A Bridge Configuration

Configure OpenClaw to invoke platform agents through the A2A bridge:

```bash
# Set A2A bridge endpoint
docker compose exec openclaw-cli openclaw config set a2a.bridge_endpoint "http://core:8080/api/v1/a2a/send-message"

# Set authorization token (same as discovery token)
docker compose exec openclaw-cli openclaw config set a2a.token "your-gateway-token-here"

# Set request timeout (default: 30 seconds)
docker compose exec openclaw-cli openclaw config set a2a.timeout 30

# Restart to apply changes
docker compose restart openclaw-gateway
```

### Verification

Check that discovery is working:

```bash
# View current tool catalog
docker compose exec openclaw-cli openclaw tools list

# Check discovery status
docker compose exec openclaw-cli openclaw config get discovery

# View logs for discovery polling
docker compose logs -f openclaw-gateway | grep discovery
```
