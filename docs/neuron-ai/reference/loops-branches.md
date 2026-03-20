# Loops & Branches

Control execution flow with conditional logic and recursive events.

## Loops

To create a loop, return the entry event of a previous node (or the same node).

```php
public function __invoke(FirstEvent $event, WorkflowState $state): FirstEvent|SecondEvent
{
    if (rand(0, 1) === 1) {
        return new FirstEvent("Looping...");
    }
    return new SecondEvent("Done");
}
```

## Branches

Conditional events allow the workflow to fork into different paths.

```php
public function __invoke(StartEvent $event, WorkflowState $state): BranchedEventA|BranchedEventB
{
    if ($state->get('type') === 'A') {
        return new BranchedEventA();
    }
    return new BranchedEventB();
}
```

The event-driven architecture allows jumping to any node, forward or backward, at any point.
