#!/usr/bin/env bash
# Post-mortem summary generator
# Runs after opencode pipeline finishes. If no summary was created by summarizer,
# generates a basic one from handoff.md state.
#
# Usage: ./builder/postmortem-summary.sh [handoff-path]

set -euo pipefail

PROJECT_ROOT="${PIPELINE_REPO_ROOT:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
HANDOFF="${1:-$PROJECT_ROOT/.opencode/pipeline/handoff.md}"
SUMMARY_DIR="$PROJECT_ROOT/builder/tasks/summary"

if [[ ! -f "$HANDOFF" ]]; then
    echo "No handoff.md found, skipping post-mortem."
    exit 0
fi

# Extract metadata from handoff (handles both **bold** and plain markdown)
PIPELINE_ID=$(grep -m1 'Pipeline ID' "$HANDOFF" | sed 's/.*: *//' | tr -d '`*' | xargs 2>/dev/null || echo "unknown")
TASK_NAME=$(grep -m1 'Task' "$HANDOFF" | head -1 | sed 's/.*Task[^:]*: *//' | tr -d '*' | head -c 100 || echo "unknown")
TIMESTAMP=$(date +%Y%m%d)

# Sanitize for filename
SLUG=$(python3 - "$PIPELINE_ID" "$TASK_NAME" <<'PYEOF'
import re
import sys

pipeline_id = sys.argv[1].strip()
task_name = sys.argv[2].strip()

def slugify(value: str) -> str:
    return re.sub(r"[^a-z0-9]+", "-", value.lower()).strip("-")

slug = slugify(pipeline_id)
if not slug or slug == "unknown":
    slug = slugify(task_name)

print((slug or "postmortem")[:50])
PYEOF
)
SUMMARY_FILE="$SUMMARY_DIR/u-${TIMESTAMP}-${SLUG}.md"

# Check if summary already exists
if ls "$SUMMARY_DIR"/*"$SLUG"* 2>/dev/null | head -1 | grep -q .; then
    echo "Summary already exists for $SLUG"
    exit 0
fi

mkdir -p "$SUMMARY_DIR"

echo "Generating post-mortem summary from handoff..."

# Build phase table by parsing handoff sections
PHASES=""
for phase in Architect Coder Reviewer Validator Tester E2E Auditor Documenter Summarizer; do
    # Extract section between "## Phase" and next "---"
    section=$(awk "/^## ${phase}$/,/^---$/" "$HANDOFF" 2>/dev/null || true)
    if [[ -z "$section" ]]; then
        continue
    fi

    # Get status
    phase_status=$(echo "$section" | grep -m1 'Status' | sed 's/.*: *//' | tr -d '`*' | xargs || echo "unknown")

    # Map to icon
    case "$phase_status" in
        done|completed)    icon="done" ;;
        failed|error)      icon="FAIL" ;;
        timeout)           icon="TIMEOUT" ;;
        pending)           icon="SKIPPED" ;;
        in_progress)       icon="INTERRUPTED" ;;
        skipped)           icon="skipped" ;;
        initialized)       icon="skipped" ;;
        *)                 icon="$phase_status" ;;
    esac

    # Get result summary (first meaningful line after Status)
    result=$(echo "$section" | grep -m1 'Result\|Summary\|Task\|Verdict' | sed 's/.*: *//' | head -c 120 || echo "")

    PHASES="${PHASES}| ${phase} | ${icon} | ${result} |\n"
done

cat > "$SUMMARY_FILE" << SUMMARY
# Pipeline Summary: ${PIPELINE_ID}

> **Auto-generated post-mortem** — summarizer did not complete. Generated from handoff.md state.

**Workflow:** Ultraworks
**Status:** FAIL

## Task

${TASK_NAME}

## Phase Results

| Phase | Status | Details |
|-------|--------|---------|
$(echo -e "$PHASES")

## Рекомендації по оптимізації

### 🔴 Pipeline incomplete: summarizer не завершився
**Що сталось:** Pipeline обірвався до фази summarizer — можливо ліміт токенів, таймаут або фейл субагента.
**Вплив:** Summary не згенеровано автоматично, ручний post-mortem.
**Рекомендація:**
- Перевірити лог: \`./builder/monitor/ultraworks-monitor.sh logs\`
- Якщо субагент підвис: перевірити модель (fast model для validator), додати timeout в Sisyphus
- Якщо ліміт токенів: зменшити scope задачі або розбити на підзадачі
- Відновити: \`/finish\` в OpenCode або перезапустити через ultraworks-monitor.sh

## Verdict

Pipeline did not complete normally. Review handoff.md for details:
\`cat .opencode/pipeline/handoff.md\`

To resume: run \`/finish\` in OpenCode or relaunch with \`ultraworks-monitor.sh launch\`.

---
*Generated: $(date -Iseconds)*
SUMMARY

echo "Post-mortem summary: $SUMMARY_FILE"
