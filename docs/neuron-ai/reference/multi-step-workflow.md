# Multi Step Workflow

Workflows can chain multiple nodes using custom events.

## Custom Events

Define classes implementing `Event` to pass data between nodes.

```php
namespace App\Neuron;

use NeuronAI\Workflow\Events\Event;

class FirstEvent implements Event
{
    public function __construct(public string $msg) {}
}
```

## Chaining Nodes

The return type of a node's `__invoke` method determines which node is triggered next.

```php
class InitialNode extends Node
{
    public function __invoke(StartEvent $event, WorkflowState $state): FirstEvent
    {
        return new FirstEvent("Data for the next step");
    }
}

class NextNode extends Node
{
    public function __invoke(FirstEvent $event, WorkflowState $state): StopEvent
    {
        echo $event->msg;
        return new StopEvent();
    }
}
```

## Execution

```php
$handler = Workflow::make()
    ->addNodes([new InitialNode(), new NextNode()])
    ->init();

$handler->run();
```
