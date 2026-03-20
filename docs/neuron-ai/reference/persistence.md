# Persistence

Persistence ensures that workflow state is preserved across pause/resume cycles, especially for interruptions.

## Implementations

- **InMemoryPersistence**: (Default) Only for the current execution cycle.
- **FilePersistence**: Stores state into a local file.
- **DatabasePersistence**: Stores state into a SQL database via PDO.

## Configuration

```php
$workflow = new MyWorkflow(new FilePersistence(__DIR__ . '/storage'));
```

## Database Schema

For SQL-based persistence, you'll need tables for storing state and resumes. (Scripts provided in overview/SQLChatHistory are similar for persistence).
