# Testing

Neuron provides a suite of fake providers and stores to make testing your agentic systems fast and deterministic without calling external APIs.

## Setup

Use `FakeAIProvider` to mock LLM responses.

```php
use NeuronAI\Testing\FakeAIProvider;
use NeuronAI\Chat\Messages\AssistantMessage;

$provider = new FakeAIProvider(
    new AssistantMessage('Hello! How can I help you?')
);

$agent = MyAgent::make()->setAiProvider($provider);
```

## Mocking RAG Components

- **FakeEmbeddingsProvider**: Generates deterministic embeddings locally.
- **FakeVectorStore**: Returns predetermined documents during similarity search.

```php
$vectorStore = new FakeVectorStore([
    new Document('This is a test document.')
]);

$agent->setVectorStore($vectorStore);
```

## Assertions

`FakeAIProvider` includes built-in assertions to verify agent behavior:

```php
$provider->assertCallCount(1);
$provider->assertLastRequestContains('Search for...');
```

## Streaming and Structured Output

- **Streaming**: `FakeAIProvider` simulates streaming by splitting responses into chunks.
- **Structured Output**: Provide a JSON string in the fake response to test schema validation and deserialization.
