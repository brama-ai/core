# Pre/Post Processor

Enhance retrieval quality with query transformation and result processing.

## Pre-Processors (Query Transformation)

Transform the user's query before retrieval to improve context matching.

### Strategies

- **Rewriting**: Rephrases the query into search-optimized terms.
- **Decomposition**: Breaks complex questions into multiple simpler sub-queries.
- **HyDE (Hypothetical Document Embeddings)**: Generates a hypothetical answer first, then searches for documents similar to that answer.

Example:

```php
protected function preProcessors(): array
{
    return [
        new QueryTransformationPreProcessor(
            provider: $this->resolveProvider(),
            transformation: QueryTransformationType::REWRITING
        ),
    ];
}
```

## Post-Processors (Re-ranking & Thresholds)

Refine retrieved results before passing them to the LLM.

### Rerankers

Use specialized models (e.g., Jina, Cohere) to re-rank documents based on actual relevance to the query, moving the most important context to the top.

### Thresholds

Filter out low-relevance documents that meet the similarity score criteria but aren't semantically strong enough.
