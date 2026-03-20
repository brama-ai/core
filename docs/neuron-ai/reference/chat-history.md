# Chat History

Chat History manages the list of messages exchanged. It automatically truncates messages to fit the LLM's context window.

## Usage

By default, Neuron uses `InMemoryChatHistory`. To persist conversation:

```php
protected function chatHistory(): ChatHistoryInterface
{
    return new FileChatHistory('/path/to/conversations', 'unique_conversation_key');
}
```

## Implementations

- **InMemoryChatHistory**: Default, stored in an array during execution.
- **FileChatHistory**: Persists to a directory based on a unique key.
- **SQLChatHistory**: Stores to a SQL database via PDO. Requires a `thread_id` to separate conversations.
- **EloquentChatHistory**: Use a Laravel Eloquent model to store messages.

### SQL Table Schema

```sql
CREATE TABLE chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    thread_id VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL,
    content TEXT NOT NULL,
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```
