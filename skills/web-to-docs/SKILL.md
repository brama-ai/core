---
name: web-to-docs
description: >
  Convert a website or page with related links into a local collection of
  Markdown files with an index. Follows project docs conventions (ua/en
  bilingual structure). Uses WebFetch — no external dependencies.
  Triggers on: "web to docs", "website to markdown", "save docs locally",
  "convert site", "download docs", "fetch docs", "scrape to markdown".
---

# Web to Docs

Fetch a web page and its related links, convert each to Markdown, and save
them as a local collection with an automatically generated index.
Follows this project's documentation conventions — bilingual folder structure
with `ua/` and `en/` subdirectories.

## When to Use

- User wants to convert online documentation into local Markdown files
- User wants to save a website section for offline reference
- User wants to import external docs into a project's `docs/` directory
- User provides a URL and asks to "convert", "download", "save", or "fetch" it as docs/markdown

## Project Documentation Conventions

**IMPORTANT**: Before writing files, read `skills/documentation/SKILL.md` —
it is the single source of truth for folder structure, language rules,
INDEX.md format, and all documentation conventions. This skill MUST follow
those rules for Steps 6-7 (writing files and generating indexes).

## Constraints

- Uses ONLY the built-in `WebFetch` tool. No external CLI (no wget, curl, puppeteer).
- WebFetch cannot access authenticated or private pages. If the URL requires login, inform the user and stop.
- Maximum pages per run: **30** (user may override)
- Maximum crawl depth: **2** (default 1; user may request 2; deeper is refused)

## Workflow

### Step 1 — Collect Parameters

Ask the user or infer from context:

| Parameter | Default | Description |
|-----------|---------|-------------|
| `url` | *(required)* | The starting URL to fetch |
| `output_dir` | `./docs/fetched/<domain-slug>/` | Base directory (lang subfolders created automatically) |
| `lang` | auto-detect | Source language: `en`, `ua`, or `both`. Auto-detected from page content. |
| `scope` | `path-prefix` | `path-prefix` = links under the same URL path prefix; `same-domain` = any link on the same host |
| `depth` | `1` | `1` = only links on the starting page; `2` = also links found on those pages |
| `max_pages` | `30` | Maximum number of pages to fetch |

If the user just provides a URL, proceed with all defaults. Do NOT ask for
parameters unless the user's intent is ambiguous.

### Step 2 — Fetch the Starting Page

1. Call `WebFetch` with the starting URL.
   - Prompt: "Extract the full page content as clean Markdown. Preserve all headings, lists, code blocks, tables, and inline formatting. Also list every hyperlink on the page as a Markdown link in a separate section at the end titled '## All Links'."
2. Handle errors:
   - Redirect to a different host → inform user, ask whether to follow.
   - Auth required / 404 / timeout → report error and stop.
3. Save the converted Markdown content (first `.md` file).

### Step 3 — Discover Links

From the WebFetch response for the starting page:

1. Extract all hyperlinks (parse `[text](url)` patterns and bare URLs).
2. Normalize each URL:
   - Resolve relative paths to absolute using the starting URL as base.
   - Strip URL fragments (`#section`).
   - Strip trailing slashes.
   - Remove query parameters unless they look like meaningful pagination (`?page=N`).
3. Filter by scope:
   - **path-prefix** (default): keep only links whose origin AND path prefix match the starting URL. Example: starting from `https://docs.example.com/v2/guide/intro` → prefix `https://docs.example.com/v2/guide/`. Links like `.../v2/guide/setup` pass; `.../blog/news` does not.
   - **same-domain**: keep only links with the same origin (scheme + host + port).
4. Exclude:
   - Non-HTML resources (`.pdf`, `.zip`, `.png`, `.jpg`, `.gif`, `.svg`, `.css`, `.js`, `.xml`, `.json`, `.woff`, `.ttf`).
   - Links to the starting page itself.
   - Anchor-only links.
5. Deduplicate using normalized URL strings.

### Step 4 — User Confirmation

Present the discovered links as a numbered list:

```
Found 12 related pages under https://docs.example.com/v2/guide/:

 1. /v2/guide/setup        — "Getting Started"
 2. /v2/guide/config       — "Configuration"
 3. /v2/guide/deployment   — "Deployment"
 ...

Enter numbers to include (e.g., "1,3,5-8"), "all", or "none":
```

- Show relative path and link text (if available).
- "all" → include everything up to `max_pages`.
- "none" → skip to Step 6 with only the starting page.
- Wait for user input before proceeding.

### Step 5 — Fetch Selected Pages

For each selected URL (respecting `max_pages`):

1. Check visited-URL set. Skip if already fetched.
2. Call `WebFetch` with the URL.
   - Prompt: "Convert this page to clean Markdown. Preserve all headings, lists, code blocks, tables, and inline formatting. Return ONLY the Markdown content."
3. Add URL to the visited set.
4. If `depth == 2`, extract links from this page using the same rules as Step 3. Queue newly discovered links (do NOT recurse further).
5. If WebFetch fails for a page, log a warning and continue. Do not abort.

**Parallelism**: issue up to 5 WebFetch calls simultaneously where possible.

After all depth-2 discoveries (if applicable), present NEW links to the user
for another confirmation round (same as Step 4), then fetch those.

### Step 6 — Write Markdown Files

For each fetched page, create a `.md` file inside the appropriate language subfolder:

1. **Create language subfolder**: `<output_dir>/<lang>/` (e.g., `docs/fetched/a2a-protocol-org/en/`).
2. **Derive filename** — see `references/filename-rules.md`. Quick rules:
   - Take URL path, remove common prefix with starting URL.
   - Join remaining segments with `-`, lowercase, replace special chars with `-`.
   - Fallback to slugified page title if path is empty/numeric.
   - Collisions: append `-2`, `-3`, etc.
   - Extension: always `.md`.
3. **Add front matter**:
   ```yaml
   ---
   source: <original URL>
   fetched: <YYYY-MM-DD>
   lang: <en|ua>
   ---
   ```
4. **Write** to `<output_dir>/<lang>/<filename>.md` using the Write tool.

### Step 7 — Generate Index Files

Create **three** index/README files following `references/index-template.md`:

#### 7a. Language-specific README: `<output_dir>/<lang>/README.md`

```markdown
# <Site or Section Title>

> Fetched from [<starting URL>](<starting URL>) on YYYY-MM-DD.
> Generated by the `web-to-docs` skill.

## Pages

- [**<Page Title>**](./<filename>.md)
  <First 1-2 sentences, ~150 chars max>

- [**<Page Title>**](./<filename>.md)
  <First 1-2 sentences, ~150 chars max>
```

- Starting page first, then remaining pages in their original link order.
- Title: first `# ` heading from the Markdown, or link text, or filename.
- Excerpt: first non-heading paragraph, truncated to ~150 chars with "...".

#### 7b. Root section README: `<output_dir>/README.md`

```markdown
# <Site or Section Title>

> Source: [<starting URL>](<starting URL>)
> Fetched: YYYY-MM-DD | Generated by `web-to-docs`

## Languages

- [English](./en/README.md)
- [Українська](./ua/README.md) *(if bilingual)*

## Language Rule

- `ua/` — канонічна українська версія
- `en/` — English version
```

This root README links to language-specific READMEs and follows the project
convention used in `docs/specs/`, `docs/agents/`, `docs/product/`.

### Step 8 — Summary

Print a summary:

```
Done! Saved X pages to <output_dir>/<lang>/:

  README.md           — Table of contents
  getting-started.md  — "Getting Started with FooBar"
  configuration.md    — "Configuration Reference"
  ...

Structure:
  <output_dir>/
  ├── README.md           — Root index with language links
  └── en/
      ├── README.md       — English section index
      ├── what-is-a2a.md
      └── ...

Skipped (errors): Y pages
  - https://example.com/broken (404)
```

## Error Handling

| Scenario | Behavior |
|----------|----------|
| Redirect to different host | Inform user, ask whether to follow |
| Fetch fails on starting page | Report error, stop entirely |
| Fetch fails on subsequent page | Log warning, skip, continue |
| Max pages reached | Inform user, stop fetching, save what was fetched |
| No links discovered | Save only starting page + minimal index |
| All links filtered out by scope | Inform user, suggest `same-domain` scope |
| Output directory has existing `.md` files | Ask user: overwrite, merge, or new directory |

## Limitations

- Cannot fetch authenticated pages (login walls, paywalls, API keys)
- Cannot execute JavaScript; SPA content may be incomplete
- WebFetch may summarize very large pages — some content loss possible
- WebFetch has a 15-minute cache; re-runs within that window return cached content
- Not suitable for full-site mirroring (use wget/httrack for that)
