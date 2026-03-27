#!/usr/bin/env bash
# Sync shared skills from skills/ into agent-specific directories.
#
# Source of truth: skills/ (committed to repo)
# Targets:
#   - .claude/skills/           (Claude Code — raw skill files)
#   - .claude/commands/skills/  (Claude Code — auto-generated slash commands)
#   - .cursor/skills/           (Cursor / Antigravity)
#   - .codex/skills/            (Codex)
#   - ../.opencode/skills/shared/ (OpenCode — shared skills alongside pipeline skills)
#
# Usage:
#   ./scripts/sync-skills.sh            # sync all targets
#   ./scripts/sync-skills.sh claude     # sync only Claude
#   ./scripts/sync-skills.sh cursor     # sync only Cursor
#   ./scripts/sync-skills.sh codex      # sync only Codex
#   ./scripts/sync-skills.sh opencode   # sync only OpenCode

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
WORKSPACE_ROOT="$(cd "$REPO_ROOT/.." && pwd)"
SOURCE="$REPO_ROOT/skills"

if [ ! -d "$SOURCE" ]; then
  echo "Error: skills/ directory not found at $SOURCE"
  exit 1
fi

sync_target() {
  local name="$1"
  local target="$2"

  mkdir -p "$target"
  rsync -a --delete "$SOURCE/" "$target/"
  echo "  Synced skills/ -> $target ($name)"
}

# Generate Claude Code slash commands from SKILL.md files.
# Each skill becomes /skills-<name> command.
generate_claude_commands() {
  local cmd_dir="$REPO_ROOT/.claude/commands/skills"
  rm -rf "$cmd_dir"
  mkdir -p "$cmd_dir"

  for skill_dir in "$SOURCE"/*/; do
    [ -d "$skill_dir" ] || continue
    local skill_name
    skill_name="$(basename "$skill_dir")"
    local skill_file="$skill_dir/SKILL.md"
    [ -f "$skill_file" ] || continue

    # Extract description from YAML frontmatter (portable across BSD/GNU sed)
    local description
    description=$(awk '
      /^---$/ { block++; next }
      block == 1 && /^description:/ {
        sub(/^description: *>? */, "")
        sub(/^"/, ""); sub(/"$/, "")
        if ($0 != "") { print; exit }
        getline
        sub(/^ +/, "")
        print
        exit
      }
    ' "$skill_file" | tr '\n' ' ' | sed 's/ *$//' | head -c 200)
    # Fallback if extraction failed
    [ -z "$description" ] && description="Run the $skill_name skill"

    # Read content after frontmatter
    local content
    content=$(sed '1,/^---$/{ /^---$/!d; }; /^---$/d' "$skill_file" | tail -n +1)

    cat > "$cmd_dir/$skill_name.md" <<CMDEOF
Load and follow the instructions from the skill file at \`.claude/skills/$skill_name/SKILL.md\`.

Apply the skill to the user's request. If the skill references files, tools, or workflows — use them.
CMDEOF
  done

  local count
  count=$(find "$cmd_dir" -name "*.md" | wc -l | tr -d ' ')
  echo "  Generated $count Claude commands in .claude/commands/skills/"
}

# Generate Codex instructions that reference skills.
generate_codex_instructions() {
  local codex_dir="$REPO_ROOT/.codex"
  mkdir -p "$codex_dir"

  cat > "$codex_dir/AGENTS.md" <<'AGENTSEOF'
# Codex Skills

This directory contains shared skills synced from `skills/` (source of truth).

## Available Skills

AGENTSEOF

  for skill_dir in "$SOURCE"/*/; do
    [ -d "$skill_dir" ] || continue
    local skill_name
    skill_name="$(basename "$skill_dir")"
    local skill_file="$skill_dir/SKILL.md"
    [ -f "$skill_file" ] || continue

    # Extract description from YAML frontmatter (handles multiline > and quoted strings)
    local desc_line
    desc_line=$(awk '
      /^---$/ { block++; next }
      block == 1 && /^description:/ {
        sub(/^description: *>? */, "")
        sub(/^"/, ""); sub(/"$/, "")
        if ($0 != "") { print; exit }
        # multiline: read next indented line
        getline
        sub(/^ +/, "")
        print
        exit
      }
    ' "$skill_file")
    [ -z "$desc_line" ] && desc_line="$skill_name skill"

    echo "- **$skill_name**: $desc_line" >> "$codex_dir/AGENTS.md"
  done

  cat >> "$codex_dir/AGENTS.md" <<'TAILEOF'

## Usage

When a user request matches a skill, read the corresponding `skills/<name>/SKILL.md` and follow its instructions.

Skills source of truth: `skills/` directory. Do not edit copies — edit the source and run `make sync-skills`.
TAILEOF

  echo "  Generated .codex/AGENTS.md with skill index"
}

targets="${1:-all}"

do_claude() {
  echo "Claude Code:"
  sync_target "skills" "$REPO_ROOT/.claude/skills"
  generate_claude_commands
}

do_cursor() {
  echo "Cursor:"
  sync_target "skills" "$REPO_ROOT/.cursor/skills"
}

do_codex() {
  echo "Codex:"
  sync_target "skills" "$REPO_ROOT/.codex/skills"
  generate_codex_instructions
}

## Generate OpenCode slash commands from SKILL.md files.
# Each skill becomes /skill-<name> command in .opencode/commands/.
# Existing non-generated commands (auto, audit, finish, implement, validate) are preserved.
generate_opencode_commands() {
  local cmd_dir="$WORKSPACE_ROOT/.opencode/commands"
  mkdir -p "$cmd_dir"

  # Clean only previously generated skill commands (have "# [auto-generated]" marker)
  grep -rl '^\# \[auto-generated\]' "$cmd_dir" 2>/dev/null | xargs rm -f 2>/dev/null || true

  for skill_dir in "$SOURCE"/*/; do
    [ -d "$skill_dir" ] || continue
    local skill_name
    skill_name="$(basename "$skill_dir")"
    local skill_file="$skill_dir/SKILL.md"
    [ -f "$skill_file" ] || continue

    # Skip if a manual command with this name already exists (without auto-generated marker)
    local target_file="$cmd_dir/$skill_name.md"
    if [ -f "$target_file" ] && ! grep -q '^\# \[auto-generated\]' "$target_file"; then
      continue
    fi

    # Extract description
    local desc
    desc=$(awk '
      /^---$/ { block++; next }
      block == 1 && /^description:/ {
        sub(/^description: *>? */, "")
        sub(/^"/, ""); sub(/"$/, "")
        if ($0 != "") { print; exit }
        getline; sub(/^ +/, ""); print; exit
      }
    ' "$skill_file")
    [ -z "$desc" ] && desc="Run the $skill_name skill"

    cat > "$target_file" <<OCEOF
# [auto-generated] by sync-skills.sh — do not edit manually
---
description: "$desc"
---

Load and follow the shared skill from \`.opencode/skills/shared/$skill_name/SKILL.md\`.

Apply the skill to the user's request. If the skill references files, tools, or workflows — use them.
OCEOF
  done

  local count
  count=$(grep -rl '^\# \[auto-generated\]' "$cmd_dir" 2>/dev/null | wc -l | tr -d ' ')
  echo "  Generated $count OpenCode commands in .opencode/commands/"
}

do_opencode() {
  echo "OpenCode:"
  sync_target "shared skills" "$WORKSPACE_ROOT/.opencode/skills/shared"
  generate_opencode_commands
}

case "$targets" in
  claude)   do_claude ;;
  cursor)   do_cursor ;;
  codex)    do_codex ;;
  opencode) do_opencode ;;
  all)      do_claude; do_cursor; do_codex; do_opencode ;;
  *)
    echo "Usage: $0 [claude|cursor|codex|opencode|all]"
    exit 1
    ;;
esac

echo "Done."
