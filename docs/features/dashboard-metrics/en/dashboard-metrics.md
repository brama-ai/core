# Dashboard Platform Metrics

## Overview

The admin dashboard displays aggregated real-time platform metrics: A2A message statistics, agent activity, and scheduler status. Data is cached for 5 minutes per section independently.

## Architecture

```
DashboardMetricsService          (DBAL queries + PSR-6 cache)
  └─► DashboardController        (passes metrics to template)
       └─► dashboard.html.twig   (three glass-card panels)
```

- **Service** (`DashboardMetricsService`) — executes SQL queries via DBAL `Connection`, caches results via `CacheItemPoolInterface`
- **Controller** (`DashboardController`) — calls `getMetrics()`, passes the array to Twig
- **Template** — renders three glass-card metric panels

## Data Sources

| Table | Metrics |
|-------|---------|
| `a2a_message_audit` | Calls 24h/7d, avg response time, success rate, top-5 skills, active agents |
| `scheduled_jobs` | Active/paused job counts |
| `scheduler_job_logs` | Last 5 executions (status, time, agent, skill) |

## Cache Behavior

- **TTL**: 300 seconds (5 minutes)
- **Key prefix**: `dashboard_metrics.`
- **Independent caching**: each section (`a2a_stats`, `agent_activity`, `scheduler_stats`) is cached separately
- On cache miss, SQL queries execute and results are stored in cache

## UI Components

Three glass-card panels on the `/admin/dashboard` page:

1. **A2A Message Stats** — calls 24h/7d, avg response time (ms), success rate (%), top-5 skills
2. **Agent Activity** — active agent count in 24h, agent list with call counts
3. **Scheduler Stats** — active/paused jobs, recent executions table

## Key Files

| File | Description |
|------|-------------|
| `apps/core/src/Dashboard/DashboardMetricsService.php` | Metrics collection service |
| `apps/core/src/Controller/Admin/DashboardController.php` | Dashboard controller |
| `apps/core/templates/admin/dashboard.html.twig` | Twig template |
| `apps/core/public/css/admin.css` | Metrics styles (glass-card grid) |

## Limitations

- Queries do not filter by `tenant_id` (multitenancy not yet implemented for metrics)
- No DB error handling — if the database is unreachable, the dashboard fails entirely
