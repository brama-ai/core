---
name: docs-manager
description: >
  Manage bilingual (UA/EN) project documentation: conventions, directory structure,
  INDEX.md maintenance, templates, and validation. Use this skill whenever work involves
  the docs/ directory — creating docs, updating docs, checking doc structure, figuring out
  where a doc should go, understanding the bilingual folder convention, writing agent PRDs,
  feature docs, or spec docs. Also use when you need to validate that docs follow the
  leaf-directory rule, update INDEX.md, or pick the right template. Triggers on: "document",
  "docs", "write docs", "add documentation", "doc structure", "INDEX.md", "bilingual",
  "ua/en", "PRD", "where should I put this doc", "doc template".
---

# Docs Manager

Manage bilingual project documentation following the folder-based language convention.

## Directory Structure Rule

```
docs/<domain>/<theme>/<chapter>/<lang>/<file>.md
```

**Key constraint: no .md files in intermediate directories.** If a directory has subdirectories, it MUST NOT contain .md files directly. Documentation files live ONLY in leaf directories.

Examples:
- `docs/agents/ua/hello-agent.md` — correct (leaf)
- `docs/agents/hello-agent.md` — WRONG (agents/ has ua/ and en/ subdirs)
- `docs/plans/mvp-plan.md` — correct (plans/ is a leaf, English-only)

The only exception is `INDEX.md` (project root) — see below.

## INDEX.md — Documentation Memory Index

`INDEX.md` (project root) is the **agent-facing index** of all documentation. It is:

- **English-only** — intended for AI agents, not humans
- **Always in the root** of `docs/` — the sole allowed .md file in `docs/` (besides no other)
- **Compact** — flat list of relative paths with one-line descriptions
- **Mandatory to update** — every Create, Delete, or Move operation MUST update `INDEX.md` (project root)
- **Links to `en/` versions** — for bilingual sections, INDEX.md always references the `en/` path (e.g., `docs/agents/en/hello-agent.md`), because `ua/` exists only for quick human browsing

Agents should load `INDEX.md` (project root) first to understand the documentation landscape before reading specific files.

## Path Schema

| Level | Meaning | Example |
|-------|---------|---------|
| domain | Top-level subject area | `agents`, `specs`, `plans` |
| theme | Grouping within domain (optional) | `prd`, `architecture` |
| chapter | Specific topic (optional) | `auth`, `core-agent` |
| lang | Language folder (`ua/`, `en/`) | For bilingual sections only |

For English-only sections, files go directly in the deepest topic folder without `ua/en` split.

## Convention

- Bilingual docs use **folder-based** separation: `ua/` (Ukrainian canonical) and `en/` (English mirror)
- `ua/` and `en/` are always the LAST level before .md files
- Developer-facing technical docs (code contracts, runbooks) stay English-only — no `ua/en` split
- Both `ua/` and `en/` files MUST have identical structure and headings; only language differs
- Templates and reusable boilerplate go in `docs/templates/` (English-only)
- Reference: `openspec/project.md` → Documentation Language

## Domains

| Domain | Path | Bilingual | Description |
|--------|------|-----------|-------------|
| agents | `docs/agents/` | yes | Agent PRDs and feature docs |
| specs | `docs/specs/` | yes | Interface specifications |
| plans | `docs/plans/` | no (English) | Development plans |
| agent-requirements | `docs/agent-requirements/` | no (English) | Agent contracts & conventions |
| neuron-ai | `docs/neuron-ai/` | no (English) | AI framework reference |
| decisions | `docs/decisions/` | no (English) | Architecture Decision Records |
| product | `docs/product/` | yes | Product vision, PRDs, brainstorms |
| templates | `docs/templates/` | no (English) | Reusable doc templates |
| features | `docs/features/` | yes | Feature documentation |
| fetched | `docs/fetched/` | per-source | External docs fetched by `web-to-docs` skill |

## Operations

### Create

Create a new documentation file.

**Input**: `<domain>/<filename>` (e.g., `agents/hello-agent`)

**Steps**:
1. Resolve target path: `docs/<domain>/`
2. Verify target is a leaf directory (no subdirectories) OR create `ua/` and `en/` subdirs
3. For bilingual: write `docs/<domain>/ua/<filename>.md` and `docs/<domain>/en/<filename>.md`
4. For English-only: write `docs/<domain>/<filename>.md`
5. Use the appropriate template (see Templates below)
6. **Update `INDEX.md` (project root)**: add the new file entry to the appropriate section
7. **Validate**: no .md files in intermediate directories after creation

### Update

Update an existing documentation file.

**Input**: `<domain>/<filename>` (e.g., `agents/hello-agent`)

**Steps**:
1. Locate both files: `docs/<domain>/ua/<filename>.md` and `docs/<domain>/en/<filename>.md`
2. If only legacy format exists, migrate to folder structure first
3. Apply changes to both files, keeping structure and headings in sync
4. Verify both files have the same sections after update

### Delete

Remove a documentation file pair.

**Input**: `<domain>/<filename>` (e.g., `agents/hello-agent`)

**Steps**:
1. Remove `docs/<domain>/ua/<filename>.md`
2. Remove `docs/<domain>/en/<filename>.md`
3. If `ua/` or `en/` folder is now empty, remove it
4. **Update `INDEX.md` (project root)**: remove the deleted file entry
5. Check for references to the deleted doc in other files and flag them

### Migrate

Convert legacy files to proper structure.

**Input**: `<domain>` or specific `<domain>/<filename>`

**Steps**:
1. Find .md files in intermediate directories (dirs that have subdirectories)
2. Determine correct leaf destination based on content and domain
3. Move files to the correct leaf directory
4. Update any cross-references in other docs
5. **Update `INDEX.md` (project root)**: fix paths for all moved files
6. **Validate**: no .md files remain in intermediate directories

## Validation

Run this check after any operation:

```
1. For each directory in docs/:
     IF directory has subdirectories AND contains .md files (except docs/INDEX.md):
       → VIOLATION — move .md files to appropriate leaf directory

2. Every .md file under docs/ (except INDEX.md) MUST have a corresponding entry in docs/INDEX.md
```

## Templates

### Agent Documentation

```markdown
# <Agent Name>

## Призначення / Purpose
<1-2 sentences>

## Функціонал / Features
- <bullet list of endpoints: POST /api/v1/a2a, GET /health, GET /api/v1/manifest, admin pages>

## Скіли / Skills
| Skill ID | Опис / Description | Ключові вхідні дані / Key Inputs |
|---|---|---|
| `agent.skill_name` | <what it does> | `field1`, `field2` |

<For each skill with non-trivial input, add a ### subsection with example JSON payload>

## База даних / Database
<Table name, columns with types, indexes. Use a markdown table>

## Технічний стек / Tech Stack
- <language, framework, DB, routing port>

## Автентифікація / Authentication
<Auth mechanism (e.g., X-Platform-Internal-Token header), curl example>

## Валідація вхідних даних / Input Validation
| Поле / Field | Правило / Rule |
|---|---|
| `field` | <required, range, allowlist, default> |

## Telegram-сповіщення / Notifications
<If agent sends notifications: mechanism, format, error handling>

## Makefile команди / Makefile Commands
- <make targets for setup, test, analyse, cs-check, migrate>

## Адмін-панель / Admin Panel
<URL, what it shows, filters>
```

Sections are ordered from "what it does" to "how to run it". Include only sections relevant to the agent — skip empty ones (e.g., skip Database if no DB, skip Notifications if none).

### Feature Documentation

```markdown
# <Feature Name>

## Огляд / Overview
<What it does, architecture>

## Швидкий старт / Quick Start
<Minimal steps to use the feature>

## Конфігурація / Configuration
<Env vars, config files, options table>

## Приклади / Examples
<Real usage examples with code blocks>
```

### Specification Documentation

```markdown
# <Spec Name>

## Огляд / Overview
<summary>

## Ендпоінти / Endpoints
<API surface>

## Формат даних / Data Format
<schemas, examples>

## Приклади / Examples
<request/response examples>
```
