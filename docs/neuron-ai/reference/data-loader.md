# Data Loader

Data loaders extract text from various sources and convert them into `Document` objects for embedding and storage.

## FileDataLoader

Processes text documents from the file system. It uses readers based on file extensions.

```php
use NeuronAI\RAG\DataLoader\FileDataLoader;

$loader = new FileDataLoader('/path/to/document.txt');
$documents = $loader->getDocuments();
```

### Supported Readers

- **PDF Reader**: Requires `poppler` utility.
- **HTML Reader**: Requires `html2text` composer package.
- **Text Reader**: Default for `.txt` files.

## StringDataLoader

Converts raw strings (e.g., from a database) into documents.

```php
use NeuronAI\RAG\DataLoader\StringDataLoader;

$loader = new StringDataLoader("Text to be indexed...");
$documents = $loader->getDocuments();
```

## Document Metadata

You can attach custom metadata to documents, which is stored in the vector store and can be used for hybrid search.

```php
foreach ($documents as $document) {
    $document->addMetadata('category', 'knowledge-base');
}
```

# Text Splitters

Large documents are split into smaller chunks (e.g., by Sentence or Delimiter) before being embedded to ensure relevant context retrieval.
