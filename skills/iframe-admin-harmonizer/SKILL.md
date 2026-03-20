---
name: iframe-admin-harmonizer
description: >
  Harmonize agent admin UIs rendered in core admin iframe. Use when an agent
  admin page looks visually inconsistent with platform admin (different
  background, spacing, navbar, card styles, table contrast) or has iframe-only
  layout issues.
---

# Skill: Iframe Admin Harmonizer

Make agent admin pages look native inside core admin iframe while preserving
standalone usability.

## When to Use

- Agent admin is opened from `/admin/agents/{name}/settings` in iframe
- Page is readable standalone but looks broken/misaligned in iframe
- Styles clash between core dark admin shell and agent page theme
- Fixed headers, full-height layouts, or white backgrounds break iframe UX

## Workflow

### 1. Verify Embed Context

1. Detect embedded mode in the agent UI:
   - Server hint: `?embedded=1`
   - Runtime fallback: `window.self !== window.top` (with `try/catch`)
2. Store mode in one place, e.g. `body[data-embedded="1"]`.

### 2. Apply Shared Visual Contract

Use a small theme layer in the agent base layout:
- Define CSS variables for surface, border, text, accent.
- Normalize cards/forms/tables to the same contrast family as core admin.
- Avoid hard white backgrounds and default Bootstrap table fills.

Reference checklist: `references/iframe-visual-checklist.md`.

### 3. Remove Iframe Anti-Patterns

- Avoid fixed full-viewport assumptions (`100vh`, large fixed offsets).
- In embedded mode, prefer `sticky`/static top bars instead of fixed headers.
- Keep content container fluid with predictable inner padding.
- Ensure modals, alerts, and empty states remain readable in dark shells.

### 4. Keep Standalone UX Intact

- Embedded styling must be conditional (`[data-embedded="1"]`) or compatible
  for both modes.
- Direct agent URLs (`/admin/sources`, `/admin/settings`) should remain usable
  outside core admin.

### 5. Validate

1. Open core agent settings iframe and check:
   - no white canvas flashes
   - cards/tables blend with host style
   - header/nav does not overlap content
2. Open direct agent admin URL and confirm no regression.
3. Run related tests (core + agent e2e if available).

