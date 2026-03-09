# Discussion summarization engine

Повідомлення з Telegram каналів зберігаються в knowledge-agent як source messages, але немає механізму автоматичного підсумку обговорень. Потрібен skill який генерує summary за період (день/тиждень), тред, або тему — з виділенням ключових рішень, посилань та учасників.

## Вимоги

### Новий A2A skill: `knowledge.summarize`
Input параметри:
- `period: string` — "day", "week", "custom" (обов'язковий)
- `date_from: string` — ISO date, початок періоду (для custom)
- `date_to: string` — ISO date, кінець періоду (для custom)
- `chat_id: string` — конкретний чат (optional, якщо не задано — всі чати)
- `topic: string` — ключові слова для фільтрації (optional)
- `format: string` — "brief" (3-5 bullet points) або "detailed" (повний summary), default "brief"

Output:
```json
{
  "summary": "...",
  "period": {"from": "2026-03-06", "to": "2026-03-07"},
  "stats": {
    "total_messages": 142,
    "unique_senders": 23,
    "active_chats": 3
  },
  "decisions": ["рішення 1", "рішення 2"],
  "links": [{"url": "...", "context": "згадували в контексті..."}],
  "top_contributors": [{"username": "...", "message_count": 15}]
}
```

### Реалізація
- Створити `SummarizationService` в knowledge-agent
- Query `knowledge_source_messages` за заданий період
- Групування повідомлень по чатах та тредах (thread_id)
- Chunk великий набір повідомлень (використати існуючий `MessageChunker`)
- Для кожного chunk: LLM summary через LiteLLM
- Фінальний LLM call: об'єднати chunk summaries в один summary
- Extraction рішень та посилань через structured output

### Зміни в KnowledgeA2AHandler
- Додати обробку intent `knowledge.summarize` / `summarize`
- Валідація input: period обов'язковий, date_from/date_to обов'язкові для custom
- Rate limiting: не більше 1 summarization request на хвилину (простий in-memory throttle)

### Зміни в SourceMessageRepository
- Додати метод `findByPeriod(DateTimeInterface $from, DateTimeInterface $to, ?string $chatId = null): array`
- Додати метод `findByTopic(string $keywords, DateTimeInterface $from, DateTimeInterface $to): array` — пошук по message_text з ILIKE

### Manifest
- Додати skill в manifest.json knowledge-agent:
```json
{
  "id": "knowledge.summarize",
  "name": "Summarize discussions",
  "description": "Generate summary of discussions for a given period with decisions and links",
  "input_schema": { ... }
}
```

### Тести
- Unit тест: SummarizationService з mock LLM response
- Unit тест: SourceMessageRepository::findByPeriod query
- Unit тест: chunk → summarize → merge pipeline
- Functional тест: A2A call з period=day

## Контекст

- Source messages table: `knowledge_source_messages`
  - Колонки: chat_id, chat_title, message_text, sender_username, message_timestamp, thread_id
  - Repository: `apps/knowledge-agent/src/Repository/SourceMessageRepository.php`
- Існуючий chunker: `apps/knowledge-agent/src/Service/MessageChunker.php`
  - Chunks by: time window 15min, max 50 messages, overlap 5
- A2A handler: `apps/knowledge-agent/src/A2A/KnowledgeA2AHandler.php`
- LiteLLM: знаходиться за `LITELLM_BASE_URL`, використовує OpenAI-compatible API
- Manifest: `apps/knowledge-agent/public/manifest.json`

## Обмеження

- Не створювати окремий agent — summarization живе в knowledge-agent
- Для великих періодів (тиждень+) використовувати map-reduce: chunk summaries → final summary
- Максимальна кількість повідомлень для обробки за один запит: 1000 (решту відсікти з попередженням)
- Summary мовою повідомлень (українська якщо більшість повідомлень українською)
