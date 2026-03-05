#!/usr/bin/env bash
# =============================================================================
# bootstrap.sh — One-shot local environment setup
# =============================================================================
# Reads .env.local and configures:
#   1. docker/openclaw/.env          (gateway token + telegram token)
#   2. .local/openclaw/state/        (directory structure)
#   3. openclaw.json                 (LLM provider + Telegram channel)
#
# Usage:
#   ./scripts/bootstrap.sh           # reads .env.local from repo root
#   ENV_FILE=.env.prod ./scripts/sh  # custom env file
# =============================================================================

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
ENV_FILE="${ENV_FILE:-$REPO_ROOT/.env.local}"

# ── Helpers ──────────────────────────────────────────────────────────────────

info()  { printf '  \033[1;34m→\033[0m %s\n' "$*"; }
ok()    { printf '  \033[1;32m✓\033[0m %s\n' "$*"; }
warn()  { printf '  \033[1;33m!\033[0m %s\n' "$*"; }
fail()  { printf '  \033[1;31m✗\033[0m %s\n' "$*" >&2; exit 1; }

load_env() {
  [ -f "$ENV_FILE" ] || fail "$ENV_FILE not found. Copy .env.local.example to .env.local first."
  set -a
  # shellcheck source=/dev/null
  . "$ENV_FILE"
  set +a
  ok "Loaded $ENV_FILE"
}

generate_token() {
  openssl rand -hex 32
}

# ── Main ─────────────────────────────────────────────────────────────────────

echo ""
echo "  AI Community Platform — Bootstrap"
echo "  ─────────────────────────────────"
echo ""

load_env

# 1. Gateway token (auto-generate if not set)
if [ -z "${OPENCLAW_GATEWAY_TOKEN:-}" ]; then
  OPENCLAW_GATEWAY_TOKEN="$(generate_token)"
  info "Auto-generated OPENCLAW_GATEWAY_TOKEN"
fi

# 2. Write docker/openclaw/.env
CLAW_ENV="$REPO_ROOT/docker/openclaw/.env"
mkdir -p "$(dirname "$CLAW_ENV")"

cat > "$CLAW_ENV" <<EOF
OPENCLAW_GATEWAY_TOKEN=${OPENCLAW_GATEWAY_TOKEN}
TELEGRAM_BOT_TOKEN=${TELEGRAM_BOT_TOKEN:-}
TELEGRAM_WEBHOOK_URL=
EOF
ok "Wrote $CLAW_ENV"

# 3. Prepare OpenClaw state directory
CLAW_STATE="$REPO_ROOT/.local/openclaw/state"
mkdir -p "$CLAW_STATE"

# 4. Build openclaw.json
CLAW_CONFIG="$CLAW_STATE/openclaw.json"

# Detect LLM provider and default model
LLM_PROVIDER=""
DEFAULT_MODEL=""
LITELLM_BASE_URL="http://litellm:4000/v1"
LITELLM_MASTER_KEY="${LITELLM_MASTER_KEY:-dev-key}"
if [ -n "${OPENROUTER_API_KEY:-}" ]; then
  # Route OpenClaw through local LiteLLM so all Minimax traffic is centralized.
  LLM_PROVIDER="litellm"
  DEFAULT_MODEL="minimax/minimax-m2.5"
elif [ -n "${OPENAI_API_KEY:-}" ]; then
  LLM_PROVIDER="openai"
  DEFAULT_MODEL="openai/gpt-4o"
elif [ -n "${ANTHROPIC_API_KEY:-}" ]; then
  LLM_PROVIDER="anthropic"
  DEFAULT_MODEL="anthropic/claude-sonnet-4-20250514"
fi

[ -n "$LLM_PROVIDER" ] || fail "No LLM provider key found in $ENV_FILE. Set OPENROUTER_API_KEY, OPENAI_API_KEY, or ANTHROPIC_API_KEY."

# Allow custom model override via LLM_MODEL env var
LLM_MODEL="${LLM_MODEL:-$DEFAULT_MODEL}"

if [ "$LLM_PROVIDER" = "litellm" ]; then
  case "$LLM_MODEL" in
    openrouter/auto)
      LLM_MODEL="minimax/minimax-m2.5"
      ;;
  esac
  LLM_MODEL="${LLM_MODEL#litellm/}"
  OPENCLAW_MODEL="litellm/${LLM_MODEL}"
else
  OPENCLAW_MODEL="$LLM_MODEL"
fi

info "LLM provider: $LLM_PROVIDER (model: $OPENCLAW_MODEL)"

# Build Telegram channel block (only if token provided)
if [ -n "${TELEGRAM_BOT_TOKEN:-}" ]; then
  CHANNELS_BLOCK=$(cat <<JSONEOF
  "channels": {
    "telegram": {
      "enabled": true,
      "botToken": "${TELEGRAM_BOT_TOKEN}",
      "dmPolicy": "pairing",
      "groupPolicy": "open",
      "groups": {
        "*": {
          "requireMention": true
        }
      },
      "streaming": "partial",
      "textChunkLimit": 4000
    }
  },
JSONEOF
)
  info "Telegram channel: enabled"
else
  CHANNELS_BLOCK=""
  warn "Telegram channel: skipped (no TELEGRAM_BOT_TOKEN)"
fi

# Build custom model provider block for LiteLLM
if [ "$LLM_PROVIDER" = "litellm" ]; then
  MODELS_BLOCK=$(cat <<JSONEOF
  "models": {
    "mode": "merge",
    "providers": {
      "litellm": {
        "baseUrl": "${LITELLM_BASE_URL}",
        "apiKey": "${LITELLM_MASTER_KEY}",
        "api": "openai-completions",
        "models": [
          {
            "id": "${LLM_MODEL}",
            "name": "${LLM_MODEL} (LiteLLM)",
            "reasoning": false,
            "input": ["text"],
            "cost": {"input": 0, "output": 0, "cacheRead": 0, "cacheWrite": 0},
            "contextWindow": 16000,
            "maxTokens": 4096
          }
        ]
      }
    }
  },
JSONEOF
)
else
  MODELS_BLOCK=""
fi

# Write the config (only if it doesn't exist or is empty)
if [ ! -s "$CLAW_CONFIG" ]; then
  cat > "$CLAW_CONFIG" <<JSONEOF
{
  "meta": {
    "lastTouchedAt": "$(date -u +%Y-%m-%dT%H:%M:%S.000Z)"
  },
  "auth": {
    "profiles": {
      "${LLM_PROVIDER}:default": {
        "provider": "${LLM_PROVIDER}",
        "mode": "api_key"
      }
    }
  },
${MODELS_BLOCK}  "agents": {
    "defaults": {
      "model": {
        "primary": "${OPENCLAW_MODEL}"
      },
      "models": {
        "${OPENCLAW_MODEL}": {}
      },
      "workspace": "/home/node/.openclaw/workspace"
    }
  },
  "tools": {
    "profile": "messaging"
  },
  "commands": {
    "native": "auto",
    "nativeSkills": "auto",
    "restart": true,
    "ownerDisplay": "raw"
  },
  "session": {
    "dmScope": "per-channel-peer"
  },
${CHANNELS_BLOCK}  "gateway": {
    "port": 18789,
    "mode": "local",
    "bind": "lan",
    "controlUi": {
      "dangerouslyAllowHostHeaderOriginFallback": true
    },
    "auth": {
      "mode": "token",
      "token": "${OPENCLAW_GATEWAY_TOKEN}"
    },
    "tailscale": {
      "mode": "off",
      "resetOnExit": false
    }
  }
}
JSONEOF
  ok "Wrote $CLAW_CONFIG"
else
  warn "openclaw.json already exists — skipping (delete it to regenerate)"
fi

# 5. Run provider onboard (non-interactive) if stack is up
if docker compose ps --status running openclaw-cli 2>/dev/null | grep -q openclaw-cli; then
  info "OpenClaw CLI is running — configuring provider key..."

  ONBOARD_FLAGS="--non-interactive --accept-risk --mode local --flow quickstart"
  ONBOARD_FLAGS="$ONBOARD_FLAGS --gateway-auth token --gateway-bind lan --gateway-port 18789"
  ONBOARD_FLAGS="$ONBOARD_FLAGS --gateway-token $OPENCLAW_GATEWAY_TOKEN"
  ONBOARD_FLAGS="$ONBOARD_FLAGS --skip-channels --skip-skills"

  if [ "$LLM_PROVIDER" = "litellm" ]; then
    ONBOARD_FLAGS="$ONBOARD_FLAGS --auth-choice custom-api-key"
    ONBOARD_FLAGS="$ONBOARD_FLAGS --custom-provider-id litellm"
    ONBOARD_FLAGS="$ONBOARD_FLAGS --custom-base-url $LITELLM_BASE_URL"
    ONBOARD_FLAGS="$ONBOARD_FLAGS --custom-model-id $LLM_MODEL"
    ONBOARD_FLAGS="$ONBOARD_FLAGS --custom-api-key $LITELLM_MASTER_KEY"
    ONBOARD_FLAGS="$ONBOARD_FLAGS --skip-health"
  else
    if [ -n "${OPENROUTER_API_KEY:-}" ]; then
      ONBOARD_FLAGS="$ONBOARD_FLAGS --openrouter-api-key $OPENROUTER_API_KEY"
    fi
    if [ -n "${OPENAI_API_KEY:-}" ]; then
      ONBOARD_FLAGS="$ONBOARD_FLAGS --openai-api-key $OPENAI_API_KEY"
    fi
    if [ -n "${ANTHROPIC_API_KEY:-}" ]; then
      ONBOARD_FLAGS="$ONBOARD_FLAGS --anthropic-api-key $ANTHROPIC_API_KEY"
    fi
  fi

  # shellcheck disable=SC2086
  if docker compose exec -T openclaw-cli openclaw onboard $ONBOARD_FLAGS 2>&1 | tail -3; then
    ok "Provider key configured"
  else
    warn "Onboard command had warnings (provider key may still need manual setup via: docker compose exec openclaw-cli openclaw onboard)"
  fi
else
  warn "OpenClaw CLI not running — run 'make up' then 'make bootstrap' again to configure provider keys"
fi

# 6. Summary
echo ""
echo "  ─────────────────────────────────"
echo "  Done! Next steps:"
echo ""
echo "    make setup        # Build containers (first time)"
echo "    make up           # Start the stack"
echo "    make migrate      # Run database migrations"
echo ""
if [ -n "${TELEGRAM_BOT_TOKEN:-}" ]; then
echo "  Telegram:"
echo "    1. Message your bot with /start"
echo "    2. Approve pairing:"
echo "       docker compose exec openclaw-cli openclaw pairing approve telegram <CODE>"
echo ""
fi
echo "  OpenClaw Control UI:"
echo "    http://localhost:8082/"
echo "    Token: ${OPENCLAW_GATEWAY_TOKEN:0:8}...${OPENCLAW_GATEWAY_TOKEN: -8}"
echo ""
