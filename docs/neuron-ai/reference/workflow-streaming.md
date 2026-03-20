# Streaming (Workflow)

Workflows support real-time updates directly to clients during execution.

## Emitting Events

Nodes can emit streaming events that are captured by the workflow handler.

## Consuming Streams

Use the `events()` method to get a generator for streaming events.

```php
$handler = $workflow->init();
foreach ($handler->events() as $event) {
    // Process real-time update
}
```

This allows complex multi-agent systems to provide ongoing feedback to users during long-running tasks.
