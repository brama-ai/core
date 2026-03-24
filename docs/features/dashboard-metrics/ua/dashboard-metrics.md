# Метрики платформи на дашборді

## Огляд

Адмін-дашборд відображає агреговані метрики платформи в реальному часі: статистику A2A-повідомлень, активність агентів та стан планувальника. Дані кешуються на 5 хвилин для кожної секції незалежно.

## Архітектура

```
DashboardMetricsService          (DBAL-запити + PSR-6 кеш)
  └─► DashboardController        (передає metrics у шаблон)
       └─► dashboard.html.twig   (три glass-card панелі)
```

- **Service** (`DashboardMetricsService`) — виконує SQL-запити через DBAL `Connection`, кешує результати через `CacheItemPoolInterface`
- **Controller** (`DashboardController`) — викликає `getMetrics()`, передає масив у Twig
- **Template** — рендерить три картки з метриками у стилі glass-card

## Джерела даних

| Таблиця | Метрики |
|---------|---------|
| `a2a_message_audit` | Виклики за 24г/7д, середній час відповіді, success rate, топ-5 скілів, активні агенти |
| `scheduled_jobs` | Кількість активних/призупинених завдань |
| `scheduler_job_logs` | Останні 5 виконань (статус, час, агент, скіл) |

## Кешування

- **TTL**: 300 секунд (5 хвилин)
- **Префікс ключів**: `dashboard_metrics.`
- **Незалежне кешування**: кожна секція (`a2a_stats`, `agent_activity`, `scheduler_stats`) кешується окремо
- При промаху кешу виконуються SQL-запити, результат зберігається у кеш

## UI-компоненти

Три панелі у стилі glass-card на сторінці `/admin/dashboard`:

1. **A2A Message Stats** — виклики за 24г/7д, середній час відповіді (мс), success rate (%), топ-5 скілів
2. **Agent Activity** — кількість активних агентів за 24г, список агентів із кількістю викликів
3. **Scheduler Stats** — активні/призупинені завдання, таблиця останніх виконань

## Ключові файли

| Файл | Опис |
|------|------|
| `apps/brama-core/src/Dashboard/DashboardMetricsService.php` | Сервіс збору метрик |
| `apps/brama-core/src/Controller/Admin/DashboardController.php` | Контролер дашборда |
| `apps/brama-core/templates/admin/dashboard.html.twig` | Twig-шаблон |
| `apps/brama-core/public/css/admin.css` | Стилі метрик (glass-card grid) |

## Обмеження

- Запити не фільтруються по `tenant_id` (мультитенантність поки не реалізована для метрик)
- Немає обробки помилок БД — при недоступності бази дашборд повністю не рендериться
