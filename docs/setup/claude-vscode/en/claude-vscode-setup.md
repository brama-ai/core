# Claude VS Code Extension Setup

## Problem
Claude VS Code extension keeps asking for permissions even in devcontainer environment, while Claude CLI works in danger mode automatically.

## Solution

### 1. Workspace Settings
Add to `.vscode/settings.json`:
```json
{
    "claude-code.permissions.defaultMode": "bypassPermissions"
}
```

### 2. DevContainer Settings
Add to `.devcontainer/devcontainer.json`:
```json
{
    "customizations": {
        "vscode": {
            "settings": {
                "claude-code.permissions.defaultMode": "bypassPermissions"
            }
        }
    }
}
```

### 3. Environment Variables (Optional)
Add to `.devcontainer/docker-compose.yml`:
```yaml
environment:
  - CLAUDE_CODE_DANGER_MODE=true
  - CLAUDE_CODE_AUTO_APPROVE=true
```

## Available Permission Modes

Based on the Claude extension schema, the following modes are available:
- `"default"` - Normal mode with permission prompts
- `"bypassPermissions"` - Automatically approve all operations (danger mode)
- `"acceptEdits"` - Automatically accept file edits only
- `"plan"` - Planning mode

## Apply Changes

After updating settings:
1. Reload VS Code window: `Cmd/Ctrl + Shift + P` → "Developer: Reload Window"
2. Or rebuild container: `Cmd/Ctrl + Shift + P` → "Dev Containers: Rebuild Container"

## Additional Configuration

For more granular control, you can use:
```json
"claude-code.permissions": {
    "defaultMode": "bypassPermissions",
    "allow": ["*"],
    "deny": [],
    "ask": []
}
```

## Notes
- The extension uses different settings than Claude CLI
- Settings hierarchy: Global VS Code → Workspace → DevContainer
- Environment variables may not affect the VS Code extension directly