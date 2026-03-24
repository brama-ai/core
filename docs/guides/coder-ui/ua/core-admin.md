# Core Admin UI для Builder-Agent

## Що це

`/admin/coder` надає web-інтерфейс для існуючого builder workflow:

- створення задач
- перегляд черги
- перегляд воркерів
- live-логи та stage timeline
- retry / cancel / priority update

## Як це працює

Поточна реалізація є phase-1 compatibility release:

- Core БД є основним джерелом стану для UI
- `builder/tasks/*` лишається сумісним runtime-шаром
- `.opencode/pipeline/*` лишається джерелом логів, summary та artifacts
- CLI monitor (`builder/monitor/pipeline-monitor.sh`) лишається підтриманим

## Основні сторінки

- `/admin/coder`
- `/admin/coder/create`
- `/admin/coder/{id}`

## Операційні команди

```bash
cd apps/brama-core
php bin/console coder:worker:start --id=worker-1
php bin/console coder:worker:status
php bin/console coder:worker:stop worker-1
```

## Обмеження першої версії

- A2A skill exposure ще не увімкнено
- pipeline як і раніше виконується через `builder/pipeline.sh`
- SSE побудований поверх змін у БД, без окремого Redis pub/sub шару
