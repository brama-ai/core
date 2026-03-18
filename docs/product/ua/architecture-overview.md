# Architecture Overview

## Goal

Створити універсальну, масштабовану фундацію для **агентно-орієнтованих рішень** (Agentic Solutions). Ця архітектура дозволяє використовувати AI-агентів та мікросервіси як незалежні "цеглинки" для розбудови систем будь-якої складності: від чистих оркестраційних платформ до складних гібридних веб-додатків з розширеними API. 

Головний принцип архітектури: **Language & Framework Agnostic**. Ядро платформи надає надійний Event Bus, a2a-маршрутизацію та інфраструктуру, до якої можна підключати модулі (агенти), написані на будь-якій мові програмування з використанням будь-яких фреймворків, без необхідності переписувати основну систему.

## Core Components

### 1. Telegram Adapter

- приймає webhook або polling events
- нормалізує події у внутрішній формат
- мапить Telegram user/chat metadata

### 2. Event Bus

- передає події до core і активних агентів
- підтримує типи:
  - `message.created`
  - `message.updated`
  - `message.deleted`
  - `command.received`
  - `schedule.tick`

### 3. Agent Registry

- реєструє доступні агенти
- зберігає їх manifest
- перевіряє, чи агент увімкнений для конкретного ком'юніті

### 4. Command Router

- розбирає chat-команди
- перевіряє права
- направляє команду в platform core або конкретний агент

### 5. Shared Services

- Postgres storage
- Redis cache / ephemeral coordination
- OpenSearch для пошуку по knowledge/messages
- RabbitMQ для queue / async integration bootstrap
- LiteLLM як локальний proxy / debug gateway для всіх LLM-запитів
- scheduler
- structured logging

## Current Local Development Runtime

Для локальної розробки вже зібраний один `docker compose` stack (`ai-community-platform`), який об'єднує:

- `Traefik` як єдиний public entry layer
- `core` як platform-owned HTTP surface на `http://localhost/`
- `openclaw-stub` як окремий runtime placeholder на `http://localhost:8082/`
- `Postgres` на `localhost:5432`
- `Redis` на `localhost:6379`
- `OpenSearch` на `http://localhost:9200/`
- `RabbitMQ` на `localhost:5672` і `http://localhost:15672/`
- `LiteLLM` на `http://localhost:4000/`

Це local development topology, а не фінальна production deployment model.

## Agent Contract

Кожен агент повинен мати:

- `manifest`
- `config schema`
- `supported commands`
- один або більше handler-ів:
  - `onMessage`
  - `onCommand`
  - `onSchedule`

## Manifest Example

```json
{
  "name": "knowledge-extractor",
  "version": "0.1.0",
  "permissions": ["moderator"],
  "commands": ["/wiki", "/wiki add"],
  "events": ["message.created", "command.received"]
}
```

## MVP Technical Boundaries

- один runtime процес
- одна база даних
- без окремого UI
- без складної асинхронної інфраструктури на старті

Примітка:

- ці обмеження описують MVP application ownership, а не кількість локальних dev-контейнерів
- у local env можуть існувати окремі інфраструктурні сервіси для розробки та інтеграційних перевірок
- LLM-виклики в локальній розробці повинні йти через `LiteLLM`, а не напряму в provider API

## Proposed First Milestone

1. Telegram adapter + command router
2. Agent registry + enable/disable flow
3. Shared storage schema
4. Knowledge Extractor
5. Locations Catalog
6. News Digest
7. Anti-fraud Signals
