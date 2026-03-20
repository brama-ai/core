# Single Step Workflow

The simplest workflow contains one node.

## Create a Workflow

Inherit from `NeuronAI\Workflow\Workflow` and define the `nodes()` method.

```php
namespace App\Neuron;

use NeuronAI\Workflow\Workflow;

class MyWorkflow extends Workflow
{
    protected function nodes(): array
    {
        return [
            new InitialNode(),
        ];
    }
}
```

## Create a Node

Extend `NeuronAI\Workflow\Node` and implement `__invoke`.

```php
namespace App\Neuron;

use NeuronAI\Workflow\Node;
use NeuronAI\Workflow\StartEvent;
use NeuronAI\Workflow\StopEvent;
use NeuronAI\Workflow\WorkflowState;

class InitialNode extends Node
{
    public function __invoke(StartEvent $event, WorkflowState $state): StopEvent
    {
        echo "Hello World!";
        return new StopEvent();
    }
}
```

## Start and Stop Events

- **StartEvent**: Triggered when the workflow starts.
- **StopEvent**: Ends execution and returns the final state.
