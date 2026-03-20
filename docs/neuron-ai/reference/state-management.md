# State Management

The `WorkflowState` object is shared across all nodes in a workflow.

## Input and Output

Provide initial data when starting and retrieve the final state when finished.

```php
$workflow = Workflow::make(state: new WorkflowState(['input_key' => 'value']))
    // ... add nodes
    ->init();

$finalState = $workflow->run();
echo $finalState->get('result_key');
```

## Within Nodes

`WorkflowState` is injected as the second argument of the `__invoke` method.

```php
public function __invoke(MyEvent $event, WorkflowState $state): StopEvent
{
    $input = $state->get('input_key');
    $state->set('result_key', 'Processed: ' . $input);
    return new StopEvent();
}
```

Using the shared state is preferred over passing all data through event properties, especially for non-contiguous steps.
