# Streaming

Stream AI responses in real-time using the `stream()` method instead of `chat()`.

## Usage

```php
$handler = MyAgent::make()->stream(new UserMessage('How are you?'));

foreach ($handler->events() as $chunk) {
    echo $chunk->content;
}
```

## Streaming Chunks

Iteration returns different chunk types:

- **TextChunk**: Piece of text.
- **ReasoningChunk**: Model's reasoning steps.
- **ToolCallChunk**: Agent asking to run a tool.
- **ToolResultChunk**: Result of a tool execution.

## Stream Adapters

Adapters translate internal events to frontend protocols:

- **Vercel AI SDK Adapter**: Support for the Vercel AI SDK protocol.
- **AG-UI Adapter**: Protocol for AG-UI interactions.
- **Custom Adapters**: Implement `StreamAdapterInterface`.

Example with adapter:

```php
foreach ($handler->events(new VercelAIAdapter()) as $chunk) {
    // formatted for Vercel AI SDK
}
```
