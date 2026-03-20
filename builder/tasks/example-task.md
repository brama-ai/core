# Add streaming support to A2A gateway

WebSocket streaming потрібен для real-time відповідей агентів замість очікування повної відповіді.

## Вимоги
- Підтримка в knowledge-agent (пріоритет)
- Зворотна сумісність з HTTP polling
- Graceful handling connection drops
- Тести на streaming endpoint

## Контекст
- A2A протокол описаний в docs/specs/en/a2a-protocol.md
- Поточний HTTP handler: apps/core/src/A2AGateway/A2AClient.php
- Symfony підтримує StreamedResponse

## Обмеження
- Не змінювати існуючий API контракт для non-streaming клієнтів
- Максимальний час streaming сесії: 5 хвилин
