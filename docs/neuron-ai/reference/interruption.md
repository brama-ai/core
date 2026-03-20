# Interruption (Human In The Loop)

Pause execution to wait for external input and resume exactly where it left off.

## How it Works

1. **Request**: A node calls `$this->interrupt()` with an `InterruptRequest`.
2. **Pause**: The workflow throws a `WorkflowInterrupt` exception and saves state.
3. **Decision**: External code (or human) provides feedback.
4. **Resume**: The workflow resumes at the interrupted node with the provided feedback.

## Implementation Example

```php
public function __invoke(InputEvent $event, WorkflowState $state): OutputEvent
{
    $response = $this->interrupt(new ApprovalRequest(
        message: 'Proceed with deletion?',
        actions: [new Action('delete_file', 'Delete', 'Delete /var/log/old.txt')]
    ));

    $action = $response->getAction('delete_file');
    if ($action->isApproved()) {
        // execute deletion
        return new OutputEvent();
    }
    return new InputEvent(); // loop back
}
```

## Handling Interruption

Catch the `WorkflowInterrupt` exception, store the data and `resumeToken`, and resume later.

```php
try {
    $handler->run();
} catch (WorkflowInterrupt $e) {
    $token = $e->getResumeToken();
    $request = $e->getRequest();
    // store and wait for user
}
```
