# Vector Store

Vector stores are used to store and perform similarity searches on text embeddings.

## Available Integrations

- **Memory**: Volatile, in-memory store for a single session.
- **File**: Persistent store using the local file system. Efficient for low volumes.
- **Pinecone**: Managed, cloud-native vector database. Supports hybrid search.
- **Elasticsearch**: Enterprise search engine with vector support and hybrid search.
- **OpenSearch**: Open-source alternative to Elasticsearch.
- **Typesense**: High-performance open-source search engine.
- **Qdrant**: High-performance vector database with strong search capabilities.
- **ChromaDB**: AI-first open-source vector database.
- **Meilisearch**: Vector search for the Meilisearch search engine.

## Usage Example (Pinecone)

```php
protected function vectorStore(): VectorStoreInterface
{
    return new PineconeVectorStore(
        key: 'PINECONE_API_KEY',
        host: 'PINECONE_HOST'
    );
}
```

## Hybrid Search & Filtering

Many vector stores (Pinecone, Elasticsearch, etc.) support metadata filtering via `addVectorStoreFilters()`:

```php
$agent->addVectorStoreFilters([
    'category' => 'legal'
]);
```
