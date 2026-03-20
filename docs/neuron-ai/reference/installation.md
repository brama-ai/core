# Installation

## Requirements

- PHP: ^8.1

## Install

Run the composer command below to install the latest version:

```bash
composer require neuron-core/neuron-ai
```

## Create an Agent

You can easily create your first agent with the command below:

```bash
./vendor/bin/neuron make:agent App\\Neuron\\MyAgent
```

Example Agent class:

```php
namespace App\Neuron;

use NeuronAI\Agent\Agent;
use NeuronAI\SystemPrompt;
use NeuronAI\Providers\Anthropic\Anthropic;
use NeuronAI\Providers\AIProviderInterface;

class MyAgent extends Agent
{
    protected function provider(): AIProviderInterface
    {
        // return an AI provider (Anthropic, OpenAI, Ollama, Gemini, etc.)
        return new Anthropic(
            key: 'ANTHROPIC_API_KEY',
            model: 'ANTHROPIC_MODEL',
        );
    }

    public function instructions(): string
    {
        return (string) new SystemPrompt(
            background: ["You are a friendly AI Agent created with Neuron framework."],
        );
    }
}
```

## Talk to the Agent

Send a prompt to the agent to get a response from the underlying LLM:

```php
use NeuronAI\Chat\Messages\UserMessage;

$message = MyAgent::make()
    ->chat(new UserMessage("Hi, who are you?"))
    ->getMessage();

echo $message->getContent();
// I'm a friendly AI Agent built with Neuron, how can I help you today?
```

## Monitoring & Debugging

The best way to inspect what exactly is going on inside your agentic system is with [Inspector](https://inspector.dev/). Set the `INSPECTOR_INGESTION_KEY` variable in your environment file:

```env
INSPECTOR_INGESTION_KEY=nwse877auxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```
