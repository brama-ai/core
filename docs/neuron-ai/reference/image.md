# Image

Neuron supports image generation through specifically designed providers or multimodal models.

## Google Gemini (Multimodal)

Google Gemini provides a full multimodality experience. You can use it to generate images from prompts within a conversation.

```php
use NeuronAI\Providers\Gemini\Gemini;

class MyAgent extends Agent
{
    protected function provider(): AIProviderInterface
    {
        return new Gemini(
            key: 'GEMINI_API_KEY',
            model: 'gemini-2.5-flash-image',
        );
    }
}

$message = MyAgent::make()
    ->chat(new UserMessage("Generate an image of a PHP conference venue."))
    ->getMessage();

$imageBase64 = $message->getImage()->getContent();
file_put_contents('cover.png', base64_decode($imageBase64));
```

## Supported Image Providers

- **Nano Banana**: (Mentioned in overview/sidebar).
- **OpenAIImage**: DALL-E integration.
- **Gemini**: Multimodal generation.

Neuron's multimodal message layer allows for multi-turn conversations about generated images.
