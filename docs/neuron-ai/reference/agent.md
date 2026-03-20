# Agent

You can create your agent by extending the `NeuronAI\Agent\Agent` class. This class automatically manages memory, tools, and function calls.

## Introduction

Creating a YouTube summarizing agent example:

```bash
./vendor/bin/neuron make:agent App\\Neuron\\YouTubeAgent
```

```php
namespace App\Neuron;

use NeuronAI\Agent\Agent;
use NeuronAI\Agent\SystemPrompt;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\Anthropic\Anthropic;

class YouTubeAgent extends Agent
{
    protected function provider(): AIProviderInterface
    {
        return new Anthropic(
            key: 'ANTHROPIC_API_KEY',
            model: 'ANTHROPIC_MODEL',
        );
    }

    protected function instructions(): string
    {
        return (string) new SystemPrompt(
            background: ["You are an AI Agent specialized in writing YouTube video summaries."],
            steps: [
                "Get the url of a YouTube video, or ask the user to provide one.",
                "Use the tools you have available to retrieve the transcription of the video.",
                "Write the summary.",
            ],
            output: [
                "Write a summary in a paragraph without using lists. Use just fluent text.",
                "After the summary add a list of three sentences as the three most important take away from the video.",
            ]
        );
    }

    protected function tools(): array
    {
        return [];
    }
}
```

## AI Provider

The `provider()` method is the only required method to implement. It returns an instance of the provider you want to use (Anthropic, OpenAI, Gemini, Ollama, etc.).

## System Instructions

`instructions()` provide directions for making the AI act according to the task. Use `SystemPrompt` to build a consistent prompt with:

- **background**: Role and macro tasks.
- **steps**: Behavioral steps for consistency.
- **output**: Explicit format requirements.

## Message

The agent accepts input as a `Message` class and returns `Message` instances. Unified interface for all interactions.

## Fluent Agent Definition

Alternatively, you can use the fluent chain of methods:

```php
$agent = Agent::make()
    ->withProvider(...)
    ->withInstructions(...)
    ->withTools([...]);
```
