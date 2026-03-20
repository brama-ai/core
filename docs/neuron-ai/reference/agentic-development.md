# Agentic Development

This documentation is also available and searchable as a Model Context Protocol (MCP) server. This allows AI assistants to access Neuron AI documentation content directly.

The MCP server is available at: [https://docs.neuron-ai.dev/~gitbook/mcp](https://docs.neuron-ai.dev/~gitbook/mcp)

## Configuration

### Claude Code

```bash
claude mcp add --transport http neuron-ai-doc https://docs.neuron-ai.dev/~gitbook/mcp
```

### VS Code

```json
"mcp": {
  "servers": {
    "neuron-ai-doc": {
      "type": "http",
      "url": "https://docs.neuron-ai.dev/~gitbook/mcp"
    }
  }
}
```

### Cursor

```json
{
  "mcpServers": {
    "neuron-ai-doc": {
      "url": "https://docs.neuron-ai.dev/~gitbook/mcp"
    }
  }
}
```

### Windsurf

```json
{
  "mcpServers": {
    "neuron-ai-doc": {
      "serverUrl": "https://docs.neuron-ai.dev/~gitbook/mcp"
    }
  }
}
```

### OpenCode

```json
{
  "$schema": "https://opencode.ai/config.json",
  "mcp": {
    "neuron-ai-doc": {
      "type": "remote",
      "url": "https://docs.neuron-ai.dev/~gitbook/mcp",
      "enabled": true
    }
  }
}
```
