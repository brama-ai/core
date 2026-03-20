# Tools & Toolkits

Tools enable Agents to interact with application services or external APIs.

## Custom Tools

You can define tools by implementing `ToolInterface` or using the `tools()` method in the Agent class.

```php
protected function tools(): array
{
    return [
        Tool::make('get_transcription', 'retrieve the YouTube video transcription')
            ->addProperty(new ToolProperty('video_url', 'the url of the video'))
            ->setCallable(function($video_url) {
                // retrieve transcription logic
            })
    ];
}
```

### Tool Max Runs

Safety mechanism to prevent infinite loops. Default is 10 calls per tool. Customize via `toolMaxRuns()` (agent level) or `setMaxRuns()` (tool level).

## Toolkits

Toolkits package multiple related tools into a single coherent interface.

### Available Toolkits

- **MySQL & PostgreSQL**: Interact with databases via PDO. Includes `SchemaTool`, `SelectTool`, and `WriteTool`.
- **FileSystem**: `DescribeDirectoryContentTool`, `ReadFileTool`, `GrepFileContentTool`, etc.
- **Tavily**: Web search, extraction, and crawling.
- **Jina**: Web search and URL reading.
- **Zep Memory**: Long-term persistent memory.
- **AWS SES**: Send emails via Simple Email Service.

Example adding a toolkit:

```php
protected function tools(): array
{
    return [
        new MySQLToolkit($pdo)
    ];
}
```

## Parallel Tool Calls

Enable parallel execution if the model asks for multiple tool calls in a single request (requires `pcntl` extension).
