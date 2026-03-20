# Middleware (Workflow)

Workflow middleware allows you to intercept and modify the execution flow at specific nodes using `before` and `after` hooks.

## Usage

Register middleware in your Workflow class:

```php
protected function middleware(): array
{
    return [
        MyNode::class => [
            new LoggingMiddleware(),
            new ValidationMiddleware(),
        ],
    ];
}
```

## Features

- **Centralized Logic**: Handle logging, validation, or transformation globally across multiple nodes.
- **Execution Control**: Prevent node execution or modify input/output.
- **Integration**: Many built-in features (like Tool Approval) are implemented as middleware.
