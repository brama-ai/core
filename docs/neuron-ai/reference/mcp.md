# MCP Connector

Model Context Protocol (MCP) allows you to connect external tools to your agent with one line of code.

## How it works

Use `McpConnector` to discover and connect tools from an MCP server.

```php
use NeuronAI\MCP\McpConnector;

class MyAgent extends Agent
{
    protected function tools(): array
    {
        return [
            ...McpConnector::make([
                'command' => 'php',
                'args' => ['/path/to/mcp_server.php'],
            ])->tools(),
        ];
    }
}
```

## Local MCP Server

For local servers, use the `command` style configuration (as shown above).

## Remote MCP Server

Connect to remote servers using SSE HTTP transport (e.g., hosted MCP servers).

## Filtering Tools

You can filter which tools to include from the MCP server if you don't want to expose everything.
