# OpenClaw (Docker)

Local config and env files for the OpenClaw services running inside the root Docker Compose stack.

For the full setup guide (from scratch), see [docs/local-dev.md](../../docs/local-dev.md#openclaw-setup).

## Files

- `.env` — gateway token + Telegram bot token
- `../../.local/openclaw/state/openclaw.json` — main OpenClaw config (gitignored)

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

# Approve Telegram pairing
docker compose exec openclaw-cli openclaw pairing approve telegram <CODE>
```
