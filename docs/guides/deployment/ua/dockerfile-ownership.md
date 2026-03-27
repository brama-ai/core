# Правило власності Dockerfile

Кожен проєкт, що розгортається, має власний `Dockerfile` у корені проєкту. Файли Docker Compose
залишаються у workspace як шар збірки.

Англійська версія: [`docs/guides/deployment/en/dockerfile-ownership.md`](../en/dockerfile-ownership.md)

## Правило

**Кожен проєкт, що розгортається, ПОВИНЕН мати власний `Dockerfile` у корені репозиторію проєкту.**

Директорія `docker/` у workspace містить лише Compose-файли та образи для інструментів workspace.
Вона не є власником Dockerfile застосунків для проєктів, що розгортаються.

## Чому

Коли `Dockerfile` знаходиться поруч із кодом, який він збирає, проєкт можна зібрати незалежно
від workspace. CI/CD-пайплайни, реєстри контейнерів та інструменти деплою можуть посилатися на
один репозиторій без необхідності мати повне дерево workspace.

## Шаблон посилання у Compose

Compose-файли посилаються на Dockerfile проєктів через `build.context` та `build.dockerfile`:

```yaml
services:
  my-service:
    build:
      context: ../my-project        # шлях до директорії проєкту
      dockerfile: Dockerfile        # Dockerfile проєкту у корені проєкту
```

- `build.context` вказує на директорію проєкту відносно розташування Compose-файлу
- `build.dockerfile` завжди `Dockerfile` (файл у корені проєкту)
- Жоден Compose-сервіс НЕ ПОВИНЕН використовувати `build.dockerfile`, що вказує у `docker/`
  для образу проєкту, що розгортається

## Поточне розташування проєктів

Усі проєкти, що розгортаються, вже відповідають цьому правилу:

| Проєкт | Розташування Dockerfile | Посилання у Compose |
|--------|------------------------|---------------------|
| brama-core | `brama-core/Dockerfile` | `context: ../brama-core`, `dockerfile: Dockerfile` |
| hello-agent | `brama-agents/hello-agent/Dockerfile` | `context: ../brama-agents/hello-agent`, `dockerfile: Dockerfile` |
| knowledge-agent | `brama-agents/knowledge-agent/Dockerfile` | `context: ../brama-agents/knowledge-agent`, `dockerfile: Dockerfile` |
| news-maker-agent | `brama-agents/news-maker-agent/Dockerfile` | `context: ../brama-agents/news-maker-agent`, `dockerfile: Dockerfile` |
| wiki-agent | `brama-agents/wiki-agent/Dockerfile` | `context: ../brama-agents/wiki-agent`, `dockerfile: Dockerfile` |
| dev-reporter-agent | `brama-agents/dev-reporter-agent/Dockerfile` | `context: ../brama-agents/dev-reporter-agent`, `dockerfile: Dockerfile` |
| website | `brama-website/Dockerfile` | `context: ../brama-website`, `dockerfile: Dockerfile` |

## Винятки

Наступні Dockerfile є образами інструментів workspace, а не проєктами, що розгортаються.
Вони звільнені від правила власності проєкту:

| Файл | Призначення |
|------|------------|
| `.devcontainer/Dockerfile` | Образ інструментів розробника для devcontainer |
| `docker/slides/Dockerfile` | Утиліта для презентацій (Slidev) |
| `templates/agent/Dockerfile` | Шаблон-скаффолд агента — не є активним збірником |

## Додавання нового проєкту

При додаванні нового проєкту, що розгортається:

1. Розмістіть `Dockerfile` у корені директорії проєкту (наприклад, `brama-agents/my-agent/Dockerfile`)
2. У Compose-фрагменті посилайтеся на нього так:
   ```yaml
   build:
     context: ../brama-agents/my-agent
     dockerfile: Dockerfile
   ```
3. **Не** розміщуйте `Dockerfile` у `docker/` або будь-якій директорії workspace

Дивіться `templates/agent/compose.fragment.yaml` для прикладу Compose-фрагменту.
