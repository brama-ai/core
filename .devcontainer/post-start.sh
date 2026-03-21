#!/usr/bin/env bash
set -euo pipefail

# Ensure mounted tool state remains writable by the vscode user after container recreate/restart.
fix_dir() {
  local dir="$1"
  if [ -e "$dir" ]; then
    sudo chown -R vscode:vscode "$dir" 2>/dev/null || true
  fi
}

# Create common user-state directories up front so CLI tools do not fail on first run.
ensure_dir() {
  local dir="$1"
  sudo mkdir -p "$dir" 2>/dev/null || true
  fix_dir "$dir"
}

ensure_dir /home/vscode/.cache
ensure_dir /home/vscode/.npm
ensure_dir /home/vscode/.bun
ensure_dir /home/vscode/.local
ensure_dir /home/vscode/.local/state
ensure_dir /home/vscode/.local/share
ensure_dir /home/vscode/.config
ensure_dir /commandhistory

fix_dir /home/vscode/.antigravity-server
fix_dir /home/vscode/.vscode-server
fix_dir /home/vscode/.local
fix_dir /home/vscode/.claude
fix_dir /home/vscode/.gemini
fix_dir /home/vscode/.config/opencode
fix_dir /home/vscode/.local/share/opencode
fix_dir /commandhistory

touch /commandhistory/.bash_history /commandhistory/.zsh_history 2>/dev/null || true
fix_dir /commandhistory

history_snippet='[ -f /workspaces/ai-community-platform/.devcontainer/shell-history.sh ] && . /workspaces/ai-community-platform/.devcontainer/shell-history.sh'

append_history_hook() {
  local rc_file="$1"
  if [ -f "$rc_file" ] && ! grep -Fq "$history_snippet" "$rc_file"; then
    printf '\n%s\n' "$history_snippet" >> "$rc_file"
  fi
}

append_history_hook /home/vscode/.bashrc
append_history_hook /home/vscode/.zshrc

# Install a devcontainer-only OpenCode override that runs in full-trust mode.
# This keeps host sessions safer while making the container workflow non-interrupting.
sudo install -d -m 755 -o vscode -g vscode /home/vscode/.config/opencode 2>/dev/null || true
sudo install -m 644 -o vscode -g vscode \
  /workspaces/ai-community-platform/.devcontainer/opencode.devcontainer.json \
  /home/vscode/.config/opencode/opencode.json 2>/dev/null || true

# Docker socket permissions are not stable across recreates on macOS Docker Desktop.
if [ -S /var/run/docker.sock ]; then
  sudo chmod 666 /var/run/docker.sock 2>/dev/null || true
fi
