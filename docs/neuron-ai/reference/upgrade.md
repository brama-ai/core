# Upgrade to v3 from v2

In this new major version, the public APIs of Neuron components weren't changed dramatically, but the underlying architecture of Agent, RAG, and the Message system have been completely rebuilt on top of the Workflow component.

## High Impact Changes

### New Agent Namespace

The `Agent` class and related classes have been moved to the `NeuronAI\Agent` namespace.

```php
use NeuronAI\Agent\Agent;
use NeuronAI\Agent\SystemPrompt;
```

### Remove chatAsync()

The `chatAsync()` method was removed. Use the new [Async](async.md) pattern.

### Agent Return Type

`chat()` now returns a workflow state instead of a `Message` instance.

```php
$state = $agent->chat(new UserMessage("..."));
$message = $state->getMessage();
```

### Message Content Blocks

Replaced the old attachment system. Content is now composed of blocks.

```php
use NeuronAI\Chat\Messages\ContentBlocks\TextContentBlock;

$message->addBlock(new TextContentBlock("..."));
```

### Streaming Chunks

Streaming now uses dedicated chunk classes: `TextChunk`, `ReasoningChunk`, `ToolCallChunk`, `ToolResultChunk`.

## New Features

- **Tool Approval**: Built-in middleware for human-in-the-loop.
- **Mistral Dedicated Provider**: Support for multi-modal and reasoning models.
- **Cohere AI Provider**.
- **Text-To-Speech Providers**.
- **Streaming Adapters**: Integrate with Vercel AI SDK, AG-UI, etc.
- **Middleware**: Control workflow execution with `before` and `after` hooks.
