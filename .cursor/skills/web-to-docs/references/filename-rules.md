# Filename Derivation Rules

## Algorithm

Given a starting URL and a page URL, derive a clean `.md` filename.

### Input

- `starting_url`: e.g., `https://docs.example.com/v2/guide/intro`
- `page_url`: e.g., `https://docs.example.com/v2/guide/advanced/config`
- `page_title`: e.g., "Advanced Configuration" (fallback)

### Steps

1. **Extract paths**:
   - `starting_path` = `/v2/guide/intro`
   - `page_path` = `/v2/guide/advanced/config`

2. **Compute common prefix**:
   - Find the longest shared directory prefix: `/v2/guide/`
   - The starting page's own filename segment (`intro`) is NOT part of the prefix.
   - Prefix = everything up to and including the last `/` of the shared path.

3. **Compute relative portion**:
   - `relative = page_path - prefix` = `advanced/config`

4. **Slugify**:
   - Replace `/` with `-`: `advanced-config`
   - Lowercase: `advanced-config`
   - Replace any character not in `[a-z0-9-]` with `-`
   - Collapse consecutive `-` into one
   - Trim leading/trailing `-`

5. **Handle empty result**:
   - If the relative portion is empty (page IS the starting URL), use `index`.
   - If the relative portion is purely numeric (e.g., `12345`), prepend `page-`: `page-12345`.
   - If still empty after slugification, use slugified `page_title`.
   - If no title available, use `page-1`, `page-2`, etc.

6. **Handle collisions**:
   - Maintain a set of assigned filenames.
   - If `name.md` already exists, try `name-2.md`, `name-3.md`, etc.

7. **Append extension**: always `.md`.

### Examples

| Starting URL | Page URL | Result |
|--------------|----------|--------|
| `https://docs.ex.com/guide/` | `https://docs.ex.com/guide/` | `index.md` |
| `https://docs.ex.com/guide/` | `https://docs.ex.com/guide/setup` | `setup.md` |
| `https://docs.ex.com/guide/` | `https://docs.ex.com/guide/advanced/config` | `advanced-config.md` |
| `https://docs.ex.com/guide/` | `https://docs.ex.com/guide/advanced/config` (collision) | `advanced-config-2.md` |
| `https://docs.ex.com/api/v1/` | `https://docs.ex.com/api/v1/12345` | `page-12345.md` |
| `https://blog.ex.com/` | `https://blog.ex.com/2024/my-post` | `2024-my-post.md` |
