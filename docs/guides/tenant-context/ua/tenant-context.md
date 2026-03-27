# Гайд для розробника: Tenant Context

## Архітектура

Ізоляція tenant-ів використовує логічне обмеження через колонки `tenant_id`. Усі tenant-aware
таблиці містять зовнішній ключ `tenant_id UUID` на таблицю `tenants`.

### Ключові компоненти

| Компонент | Розташування | Призначення |
|-----------|--------------|-------------|
| `TenantContext` | `src/Tenant/TenantContext.php` | Request-scoped сервіс з поточним tenant |
| `TenantContextListener` | `src/Tenant/TenantContextListener.php` | Встановлює TenantContext із сесії на кожен request |
| `TenantRepository` | `src/Tenant/TenantRepository.php` | DBAL-запити для tenant-ів і pivot `user_tenant` |
| `TenantVoter` | `src/Security/TenantVoter.php` | RBAC для TENANT_VIEW/EDIT/DELETE |
| `AgentVoter` | `src/Security/AgentVoter.php` | RBAC для AGENT_INSTALL/MANAGE |
| `TenantDeletionGuard` | `src/Tenant/TenantDeletionGuard.php` | Забороняє видалення tenant-ів з активними ресурсами |

### Модель даних

```
users (еволюція admin_users)
├── id (serial, legacy PK)
├── uuid (UUID, новий логічний PK)
├── username, email, password, roles
└── created_at, updated_at

tenants
├── id (UUID)
├── name, slug (unique)
├── enabled
└── created_at, updated_at

user_tenant (pivot)
├── user_id (FK → users.uuid)
├── tenant_id (FK → tenants.id)
├── role ('owner', 'admin', 'member')
└── joined_at
```

### Tenant-aware таблиці

Ці таблиці мають колонку `tenant_id`:
- `agent_registry` — NOT NULL, ON DELETE RESTRICT, unique по `(name, tenant_id)`
- `scheduled_jobs` — NOT NULL, ON DELETE CASCADE, unique по `(agent_name, job_name, tenant_id)`
- `agent_registry_audit` — nullable, ON DELETE SET NULL
- `scheduler_job_logs` — nullable, ON DELETE SET NULL
- `a2a_message_audit` — nullable, ON DELETE SET NULL

## Використання в репозиторіях

Репозиторії інжектять `TenantContext` і scope-ять запити:

```php
// Для user-facing запитів (admin panel, API)
$tenantId = $this->tenantContext->requireTenantId();
$this->connection->fetchAllAssociative(
    'SELECT * FROM agent_registry WHERE tenant_id = :tenantId',
    ['tenantId' => $tenantId],
);

// Для background-процесів (scheduler, health poller)
// Не scope-ити — вони працюють глобально
$this->connection->fetchAllAssociative(
    'SELECT * FROM scheduled_jobs WHERE enabled = TRUE AND next_run_at <= now()',
);
```

## RBAC

### Ієрархія ролей

```
ROLE_SUPER_ADMIN > ROLE_ADMIN > ROLE_USER
```

### Атрибути voter-ів

| Атрибут | Voter | Потрібна роль tenant-а |
|---------|-------|------------------------|
| TENANT_VIEW | TenantVoter | будь-який member |
| TENANT_EDIT | TenantVoter | owner або admin |
| TENANT_DELETE | TenantVoter | тільки owner |
| AGENT_INSTALL | AgentVoter | owner або admin |
| AGENT_MANAGE | AgentVoter | owner або admin |

`ROLE_SUPER_ADMIN` обходить усі перевірки voter-ів.

## Тестування

У functional тестах модуль `Helper\\Functional` автоматично встановлює дефолтний tenant context
(`00000000-0000-4000-a000-000000000001`) перед кожним тестом. Додатковий setup не потрібен.

Для unit тестів створи `TenantContext` і виклич `set()`:

```php
$tenantContext = new TenantContext();
$tenantContext->set(new Tenant('test-id', 'Test', 'test', true, new \DateTimeImmutable(), new \DateTimeImmutable()));
```

## Міграція з `admin_users`

Таблицю `admin_users` було перейменовано в `users` з такими доповненнями:
- `uuid` (UUID, unique) — логічний primary key для всіх нових зв'язків
- `email` (unique) — заповнюється як `{username}@localhost`
- `created_at`, `updated_at` timestamps
- Default role для нових користувачів змінено з `ROLE_ADMIN` на `ROLE_USER`
- Існуючого admin підвищено до `ROLE_SUPER_ADMIN`
