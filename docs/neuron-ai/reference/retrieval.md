# Retrieval

Retrieval logic defines how context is gathered from external data sources.

## Similarity Retrieval

Default strategy that queries the vector store for the most semantically similar documents.

```php
protected function retrieval(): RetrievalInterface
{
    return new SimilarityRetrieval(
        $this->resolveVectorStore(),
        $this->resolveEmbeddingsProvider()
    );
}
```

## Retrieval as a Tool

Enable agents to decide _when_ to search for knowledge by exposing retrieval as a tool.

```php
class MyAgent extends Agent
{
    protected function tools(): array
    {
        return [
            new RetrievalTool(new SimilarityRetrieval(...))
        ];
    }
}
```

## RAPTOR

(Specialized retrieval module mentioned in overview, likely for recursive abstractive processing).

By implementing `RetrievalInterface`, you can create custom retrieval behaviors tailored to your specific data sources or domain needs.
