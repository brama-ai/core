# Skill: Documentation

Manage bilingual project documentation following the folder-based language convention.

## Workspace vs Project Docs

This skill is **project-scoped**, even when you are operating from a multi-project workspace.

- Resolve `docs/` relative to the target project, not automatically relative to the workspace root.
- For the core platform, `docs/` means `brama-core/docs/`.
- Workspace-root docs are reserved for shared runtime/developer workflow material only: workspace setup, shared tooling, orchestration, deployment shell procedures, and cross-project operator routines.
- Project docs should contain product behavior, feature docs, contracts, local-dev guides, deployment/run commands for that project, cron/scheduler operations, and project-specific admin/runtime procedures.

If the request is about how the workspace is organized, the workspace shell, or cross-project operations, update workspace docs/instructions. If the request is about a specific product or agent, update that project's `docs/`.

## Root Entry Docs

Human-facing onboarding and install entrypoints MAY live in the repository root as short navigation documents.

- Treat root `.md` files as entrypoints, not as the canonical long-form documentation.
- Keep detailed guides under the target project's `docs/` tree.
- Use stable, conventional names for onboarding/install flows.
- Prefer links from root entry docs into canonical docs instead of duplicating long sections.

Recommended root filenames:

- Workspace root: `INSTALL.md` — OSS/public installation entrypoint for the whole workspace/runtime shell.
- Project root: `QUICKSTART.md` — fastest supported path to get the product running.
- Project root: `DEVELOPMENT.md` — local development setup and daily workflow.
- Project root: `KUBERNETES.md` — Kubernetes or k3s deployment and operations.

For `brama-core`, these root files are allowed even though the detailed source content belongs in `brama-core/docs/`.

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

`INDEX.md` (project root docs root) is the **agent-facing index** of all documentation. It is:

- **English-only** — intended for AI agents, not humans
- **Always in the root** of `docs/` — the sole allowed .md file in `docs/` (besides no other)
- **Compact** — flat list of relative paths with one-line descriptions
- **Mandatory to update** — every Create, Delete, or Move operation MUST update `INDEX.md` (project root)
- **Links to `en/` versions** — for bilingual sections, INDEX.md always references the `en/` path (e.g., `docs/agents/en/hello-agent.md`), because `ua/` exists only for quick human browsing

Agents should load `INDEX.md` from the target project's docs root first to understand the documentation landscape before reading specific files.

## ROADMAP.md — Documentation Change Ledger

`ROADMAP.md` in the target project root is the human-facing progress ledger for notable documentation and product direction changes.

- For the core platform, use `brama-core/ROADMAP.md` as the canonical roadmap file.
- Every substantive documentation update MUST include a check whether `ROADMAP.md` should also be updated.
- Update `ROADMAP.md` when docs change product direction, onboarding flow, supported deployment paths, major setup steps, or contributor-facing workflow expectations.
- If a documentation change is purely editorial and does not affect direction or expected workflows, explicitly verify that no roadmap update is needed.
- Documentation work should stay consistent with `ROADMAP.md`; if the roadmap and docs disagree, resolve the mismatch instead of leaving both states in place.

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
7. **Review `ROADMAP.md`**: add or adjust an entry if the new doc changes roadmap-facing direction, setup, deployment, or contributor workflow
8. **Validate**: no .md files in intermediate directories after creation

### Update

Update an existing documentation file.

**Input**: `<domain>/<filename>` (e.g., `agents/hello-agent`)

**Steps**:
1. Locate both files: `docs/<domain>/ua/<filename>.md` and `docs/<domain>/en/<filename>.md`
2. If only legacy format exists, migrate to folder structure first
3. Apply changes to both files, keeping structure and headings in sync
4. Verify both files have the same sections after update
5. **Review `ROADMAP.md`**: update it whenever the documentation change affects roadmap-visible setup, deployment, direction, or workflow expectations

### Delete

Remove a documentation file pair.

**Input**: `<domain>/<filename>` (e.g., `agents/hello-agent`)

**Steps**:
1. Remove `docs/<domain>/ua/<filename>.md`
2. Remove `docs/<domain>/en/<filename>.md`
3. If `ua/` or `en/` folder is now empty, remove it
4. **Update `INDEX.md` (project root)**: remove the deleted file entry
5. Check for references to the deleted doc in other files and flag them
6. **Review `ROADMAP.md`**: remove or reword roadmap references that point to deleted or superseded documentation

### Migrate

Convert legacy files to proper structure.

**Input**: `<domain>` or specific `<domain>/<filename>`

**Steps**:
1. Find .md files in intermediate directories (dirs that have subdirectories)
2. Determine correct leaf destination based on content and domain
3. Move files to the correct leaf directory
4. Update any cross-references in other docs
5. **Update `INDEX.md` (project root)**: fix paths for all moved files
6. **Review `ROADMAP.md`**: update links or wording if the migration changes onboarding or contributor entrypoints
7. **Validate**: no .md files remain in intermediate directories

## Validation

Run this check after any operation:

```
1. For each directory in docs/:
     IF directory has subdirectories AND contains .md files (except docs/INDEX.md):
       → VIOLATION — move .md files to appropriate leaf directory

2. Every .md file under docs/ (except INDEX.md) MUST have a corresponding entry in docs/INDEX.md

3. If the documentation change affects setup, deployment, onboarding, contributor workflow, or project direction:
     `ROADMAP.md` MUST be reviewed and updated if needed

4. Root entry docs (`INSTALL.md`, `QUICKSTART.md`, `DEVELOPMENT.md`, `KUBERNETES.md`) MUST stay short and point to canonical docs instead of duplicating full guides
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
