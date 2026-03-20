# AI Provider

Neuron supports a wide range of AI providers. The `provider()` method in your agent class should return an instance of one of these providers.

## Supported Providers

- **Anthropic**: Support for Claude models.
- **OpenAI / OpenAIResponses**: Support for GPT models (new Responses API or old Completions API).
- **AzureOpenAI**: Connect to OpenAI models on Azure.
- **OpenAILike**: For providers with OpenAI-compatible APIs.
- **Ollama**: For running models locally.
- **Gemini / Gemini Vertex AI**: Support for Google's Gemini models.
- **Mistral**: Support for Mistral AI models.
- **HuggingFace**: Interface with HuggingFace models.
- **Deepseek**: Support for Deepseek models.
- **Grok (X-AI)**: Support for xAI's Grok.
- **AWS Bedrock Runtime**: Support for models via AWS Bedrock (requires `aws/aws-sdk-php`).
- **Cohere**: Support for Cohere models.

## Usage Example (Anthropic)

```php
protected function provider(): AIProviderInterface
{
    return new Anthropic(
        key: 'ANTHROPIC_API_KEY',
        model: 'claude-3-5-sonnet-20240620',
        parameters: [
            'temperature' => 0.7,
        ]
    );
}
```

## Custom Provider

To implement a custom provider, implement the `AIProviderInterface`:

```php
use NeuronAI\Providers\AIProviderInterface;

class MyCustomProvider implements AIProviderInterface
{
    public function chat(array $messages, array $tools = []): AssistantMessage
    {
        // Implementation logic
    }
}
```
