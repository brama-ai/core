# Embeddings Provider

Embeddings providers convert text into numerical vectors that represent the semantic meaning of the text.

## Available Providers

- **Ollama**: Local embeddings execution.
- **Voyage AI**: Specialized high-quality embeddings.
- **OpenAI**: Industry standard embeddings.
- **Gemini**: Google's embedding models.
- **AWS Bedrock**: Enterprise-grade embeddings via AWS.

## Usage

In your RAG agent, implement the `embeddings()` method:

```php
protected function embeddings(): EmbeddingsProviderInterface
{
    return new OpenAIEmbeddings(
        key: 'OPENAI_API_KEY',
        model: 'text-embedding-3-small'
    );
}
```

## Custom Provider

Implement `EmbeddingsProviderInterface` to add support for other embedding models or services.
