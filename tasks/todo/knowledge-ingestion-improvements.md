# Knowledge ingestion: typed entity extraction

Поточна knowledge pipeline витягує з повідомлень generic "knowledge entries" з title/body/tags/category. Але реальні дані містять структуровані сутності: FAQ (питання-відповідь), інструменти/сервіси, контакти людей, проекти, топіки обговорень. Потрібно розширити extraction pipeline для розпізнавання та збереження типізованих сутностей.

## Вимоги

### Нові типи сутностей
Розширити workflow для витягування наступних типів (entity_type):
1. **faq** — питання та відповідь (fields: question, answer, context)
2. **tool** — інструмент/сервіс/бібліотека (fields: name, description, url, category)
3. **person** — контакт/експерт (fields: name, username, expertise, context)
4. **project** — проект чи ініціатива (fields: name, description, status, url)
5. **topic** — тематичне обговорення (fields: title, summary, key_points)
6. **knowledge** — поточний generic тип (залишається для всього що не підпадає під інші)

### Зміни в ExtractKnowledge node
- Оновити `ExtractedKnowledge` DTO:
  - Додати `entityType: string` (один з типів вище)
  - Додати `structuredData: array` (типізовані fields залежно від entityType)
  - Зберегти існуючі поля (title, body, tags, category, treePath) для зворотної сумісності
- Оновити system prompt для LLM щоб розпізнавав типи сутностей
- AnalyzeMessages node — оновити для кращого визначення цінності (FAQ і tools завжди valuable)

### Зміни в OpenSearch schema
- Додати поле `entity_type: keyword` в mapping
- Додати поле `structured_data: object` (dynamic mapping)
- Оновити OpenSearchIndexManager для нових полів
- Переіндексація не потрібна якщо додаємо нові поля (OpenSearch dynamic mapping)

### Зміни в search
- Розширити `search_knowledge` skill для фільтрації по `entity_type`
- Додати параметр `entity_type` в input_schema (optional, для фільтрації)
- Boost FAQ results коли query виглядає як питання

### Зміни в KnowledgeTreeBuilder
- Враховувати `entity_type` при побудові дерева
- Grouping: `Technology/Tools/...`, `Community/People/...`, `FAQ/...`

### Тести
- Unit тест: AnalyzeMessages правильно оцінює FAQ як valuable
- Unit тест: ExtractKnowledge правильно визначає entity_type
- Unit тест: Structured data для кожного типу має правильні поля
- Unit тест: Search з фільтром по entity_type
- Оновити існуючі тести в `apps/knowledge-agent/tests/`

## Контекст

- Workflow: `apps/knowledge-agent/src/Workflow/KnowledgeExtractionWorkflow.php`
  - AnalyzeMessages: `ExtractKnowledge.php` — node 1 (analyze)
  - ExtractKnowledge: `ExtractKnowledge.php` — node 2 (extract)
  - EnrichMetadata: `EnrichMetadata.php` — node 3 (enrich)
- DTO: `ExtractedKnowledge` має поля title, body, tags, category, treePath
- OpenSearch index mapping: `apps/knowledge-agent/src/OpenSearch/OpenSearchIndexManager.php`
- Search: `apps/knowledge-agent/src/A2A/KnowledgeA2AHandler.php` — `search_knowledge` skill
- Tree: `apps/knowledge-agent/src/Service/KnowledgeTreeBuilder.php`
- Worker: `apps/knowledge-agent/src/Command/KnowledgeWorkerCommand.php`
- Existing tests: `apps/knowledge-agent/tests/Unit/A2A/`

## Обмеження

- Не ламати поточний формат knowledge entries — entity_type=knowledge для всього існуючого
- OpenSearch mapping зміни мають бути backward-compatible (нові поля, не зміна існуючих)
- LLM structured output повинен залишатись детермінованим (explicit JSON schema)
- Не видаляти існуючі поля з ExtractedKnowledge DTO
