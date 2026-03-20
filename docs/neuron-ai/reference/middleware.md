# Middleware

Interact with the agent execution flow using middleware. Agents are built on top of the Workflow component, allowing you to hook into different stages.

## Agent Workflow

The agent execution follows a structured workflow (ChatNode, ToolNode, etc.). Middleware can be attached to these nodes using `before` and `after` hooks.

## Built-in Middleware

### Tool Approval (Human In The Loop)

Pause execution until a human approves or rejects a tool call.

```php
protected function middleware(): array
{
    return [
        ToolNode::class => [
            new ToolApproval(tools: [BuyTicketTool::class])
        ],
    ];
}
```

When a tool requires approval, a `WorkflowInterrupt` exception is thrown. You must catch it, store the `resumeToken`, and present a UI to the user.

### Conditional Approval

You can Associated a callback to tools for custom approval logic:

```php
new ToolApproval(
    tools: [
        BuyTicketTool::class => function(array $args) {
            return $args['price'] > 100; // only approve if price > 100
        }
    ]
)
```

### Context Summarization

Automatically summarizes chat history when approaching token limits.

```php
new ContextSummarizer(maxTokens: 30000, messagesToKeep: 10)
```

Attach this to `ChatNode`, `StreamingNode`, and `StructuredNode`.
