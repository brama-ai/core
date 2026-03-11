---
name: monitor-version
description: >
  Auto-bump pipeline monitor version when scripts/pipeline-monitor.sh is modified.
  Triggers automatically as a post-edit convention — not user-invocable directly.
  When any change is made to pipeline-monitor.sh, increment the patch version
  in the "# Version:" header comment. Triggers on: "pipeline-monitor", "monitor version",
  "bump monitor".
---

# Monitor Version Bump

Automatically increment the version in `scripts/pipeline-monitor.sh` whenever the file is modified.

## Convention

The version lives on line 4 of `scripts/pipeline-monitor.sh` in the format:

```
# Version: X.Y.Z
```

### Versioning rules

- **Patch** (0.6.0 → 0.6.1): bug fixes, minor tweaks, style changes
- **Minor** (0.6.1 → 0.7.0): new features (new key binding, new tab, new view mode)
- **Major** (0.7.0 → 1.0.0): breaking changes to keyboard shortcuts or tab layout

## Workflow

### When modifying `scripts/pipeline-monitor.sh`:

1. Read the current version from the `# Version:` line (line 4)
2. Determine bump type based on the nature of the change:
   - Bug fix / refactor / cosmetic → patch bump
   - New feature (key, tab, view, action) → minor bump
   - Breaking change to existing behavior → major bump
3. Update the `# Version:` line with the new version
4. This step should be done as part of the same edit — not as a separate commit

### Example

Before (bug fix to log rendering):
```
# Version: 0.6.0
```

After:
```
# Version: 0.6.1
```

Before (added [l] key for task logs):
```
# Version: 0.5.0
```

After:
```
# Version: 0.6.0
```
