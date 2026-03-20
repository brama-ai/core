# Messages

Neuron provides a unified messaging layer to interact with multiple AI providers (OpenAI, Anthropic, Gemini, Ollama, etc.) with a single abstraction.

## What is a Message

Messages are the fundamental unit of context. They contain:

- **Role**: Identifies the message type (e.g., user, assistant).
- **Content Blocks**: The actual content (text, images, audio, files).
- **Metadata**: Optional fields for LLM response information.

Example:

```php
use NeuronAI\Chat\Messages\UserMessage;

$response = MyAgent::make()
    ->chat(new UserMessage("Hi, who are you?"))
    ->getMessage();

echo $response->getContent();
```

## Content Blocks

A message's content is a list of objects extending `ContentBlock`. Types include:

- `TextContent`: Standard text.
- `ReasoningContent`: Captured reasoning steps (for reasoning models).
- `Image`: For multimodal models.
- `File` and `File ID`: Reference files by URL, base64, or ID.
- `Audio` and `Video`.

### Text

```php
use NeuronAI\Chat\Messages\ContentBlocks\TextContent;

$message = new UserMessage("Hi");
$message->addContent(new TextContent("My name is John."));
```

### Reasoning

Automatically captured by Neuron.

```php
foreach ($response->getContentBlocks() as $block) {
    if ($block instanceof ReasoningContent) {
        echo "Reasoning: " . $block->content;
    }
}
```

### File ID

Reference files already uploaded to the provider platform to save tokens.

```php
$message->addContent(new FileBlock($fileId, SourceType::ID));
```
