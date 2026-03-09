#!/usr/bin/env bash
# Pipeline monitor — Ink (React for CLI) version
# Usage: ./scripts/pipeline-monitor-ink.sh [tasks-dir]
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$SCRIPT_DIR/monitor" && exec node index.js "$@"
