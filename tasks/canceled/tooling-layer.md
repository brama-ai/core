# Tooling layer (web search, URL fetch, GitHub, docs reader, etc.)

## Причина скасування

Задача занадто широка і абстрактна для одного pipeline run. Більшість "tools" — це окремі інтеграції які варто додавати інкрементально під конкретні use cases:

- **Web search / URL fetch** — вже є в news-maker (trafilatura + requests)
- **Vector search** — вже є в knowledge-agent (OpenSearch KNN)
- **DB query** — вже є через Doctrine DBAL в кожному агенті
- **Telegram** — вже є інтеграція в dev-reporter через Bot API

Кожен інструмент краще додавати як окремий skill до конкретного агента коли з'являється реальний use case, а не проектувати universal tooling layer наперед.

Рекомендація: створювати окремі задачі на конкретні інструменти по мірі потреби.
