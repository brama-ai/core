# Audio

Neuron supports audio generation (Text-To-Speech) and likely speech-to-text through its provider system.

## As an Agent Provider

You can set an audio provider as the main provider for an agent to generate audio responses.

```php
use NeuronAI\Providers\OpenAI\Audio\OpenAITextToSpeech;

class MyAgent extends Agent
{
    protected function provider(): AIProviderInterface
    {
        return new OpenAITextToSpeech(
            key: 'OPENAI_API_KEY',
            model: 'gpt-4o-mini-tts',
            voice: 'alloy',
        );
    }
}

// Run the agent
$message = MyAgent::make()
    ->chat(new UserMessage("Hi!"))
    ->getMessage();

// Retrieve audio (base64)
$audioBase64 = $message->getAudio()->getContent();
file_put_contents('speech.mp3', base64_decode($audioBase64));
```

## Supported Audio Providers

- **OpenAI Audio**: Text-to-speech with various voices.
- **ElevenLabs**: High-quality AI voices (mentioned in overview).

## Direct Use

Providers can also be used directly without an agent for simple TTS/STT tasks.
