# RAG (Retrieval-Augmented Generation)

RAG is the process of providing references to a knowledge base outside of the LLM's training data before generating a response. It extends LLM capabilities to specific domains or internal knowledge without retraining.

## How it works

With RAG, an information retrieval component pulls relevant information from a data source based on the user query. This information is then passed to the LLM along with the original query to generate an accurate response.

## RAG Agent Example

```php
namespace App\Neuron;

use NeuronAI\RAG\RAG;
use NeuronAI\Providers\Anthropic\Anthropic;
use NeuronAI\RAG\Embeddings\OpenAI\OpenAIEmbeddings;
use NeuronAI\RAG\VectorStore\MemoryVectorStore;

class MyChatBot extends RAG
{
    protected function provider(): AIProviderInterface
    {
        return new Anthropic(key: '...', model: '...');
    }

    protected function embeddings(): EmbeddingsProviderInterface
    {
        return new OpenAIEmbeddings(key: '...');
    }

    protected function vectorStore(): VectorStoreInterface
    {
        return new MemoryVectorStore();
    }
}
```

## Core Components

- **Data Loader**: Extracts text from files or strings.
- **Embeddings Provider**: Converts text into numerical vectors.
- **Vector Store**: Stores and searches vectors.
- **Retrieval**: Logic to retrieve relevant context.
- **Pre/Post Processors**: Query transformation and result re-ranking.
