<!-- priority: 6 -->
<!-- batch: 20260309_130350 | status: fail | duration: 1s | branch: pipeline/workflow-orchestration-deterministic-agent-pipelin -->
# Workflow orchestration: deterministic agent pipelines

Зараз агенти можуть викликати один одного тільки через agent:chat (LLM-driven tool calls) або напряму через A2AClient. Немає способу описати детермінований pipeline з кроків: "спочатку витягни знання → потім підсумуй → потім відправ в Telegram". Потрібна workflow engine в core яка дозволяє описувати і виконувати multi-step agent pipelines.

## Вимоги

### WorkflowDefinition
- Workflow описується як ordered list of steps в YAML або PHP array:
```yaml
name: daily_digest
description: "Daily knowledge digest pipeline"
steps:
  - id: extract
    skill: knowledge.summarize
    input:
      period: day
      format: detailed
    timeout_seconds: 120
    retry: 2

  - id: format
    skill: hello.greet
    input:
      message: "{{ steps.extract.output.summary }}"
    depends_on: [extract]
    timeout_seconds: 30

  - id: notify
    skill: dev_reporter.report_pipeline
    input:
      task: "Daily Digest"
      status: "{{ steps.format.output ? 'pass' : 'fail' }}"
      summary: "{{ steps.extract.output.summary }}"
    depends_on: [format]
    timeout_seconds: 30
```

### WorkflowEngine (Service)
- `execute(WorkflowDefinition $workflow, string $traceId): WorkflowResult`
- Виконує steps послідовно (або паралельно якщо нема depends_on між ними)
- Кожен step — виклик через `A2AClient::invoke()`
- Output кожного step зберігається і доступний наступним через template syntax `{{ steps.{id}.output.{field} }}`
- Timeout per step + timeout для всього workflow
- Retry policy per step: при fail — retry N раз з backoff
- Execution trace: записувати кожен step з timing, input, output, status

### Database
- Таблиця `workflow_definitions`:
  - `id UUID PRIMARY KEY`
  - `name VARCHAR(128) UNIQUE`
  - `description TEXT`
  - `definition JSONB NOT NULL` — workflow steps
  - `enabled BOOLEAN DEFAULT TRUE`
  - `created_at TIMESTAMPTZ`
  - `updated_at TIMESTAMPTZ`

- Таблиця `workflow_executions`:
  - `id UUID PRIMARY KEY`
  - `workflow_id UUID REFERENCES workflow_definitions`
  - `trace_id VARCHAR(128)`
  - `status VARCHAR(32)` — pending/running/completed/failed
  - `started_at TIMESTAMPTZ`
  - `finished_at TIMESTAMPTZ`
  - `steps_log JSONB` — масив з результатами кожного step

### Template Engine
- Простий template resolver для `{{ steps.{id}.output.{path} }}`
- Не потрібен повноцінний Twig — достатньо regex-based підстановки
- Підтримка вкладених шляхів: `{{ steps.extract.output.stats.total_messages }}`
- Підтримка default values: `{{ steps.extract.output.summary | default:"N/A" }}`

### Admin UI
- Сторінка `/admin/workflows`
- Список workflows з кнопкою "Run Now"
- Сторінка деталей execution: timeline кроків з status/duration
- Логи кожного step з input/output

### A2A Skill
- Додати skill `core.run_workflow` для запуску workflow через A2A
- Input: `workflow_name: string`, `overrides: object` (optional input overrides)

### Тести
- Unit тест: WorkflowDefinition parsing з YAML
- Unit тест: Template resolver з nested paths
- Unit тест: Step execution з mock A2AClient
- Unit тест: Retry policy та timeout handling
- Functional тест: end-to-end workflow execution

## Контекст

- A2AClient: `apps/core/src/A2AGateway/A2AClient.php` — `invoke($skill, $input, $traceId, $requestId)`
- EventBus (pub/sub pattern): `apps/core/src/EventBus/EventBus.php` — можна використати як reference
- SkillCatalog: `apps/core/src/A2AGateway/SkillCatalogBuilder.php` — список доступних skills
- Langfuse: `apps/core/src/Observability/LangfuseIngestionClient.php` — для tracing workflow execution
- Міграції: `apps/core/migrations/`
- Symfony YAML: `symfony/yaml` вже є в залежностях

## Обмеження

- Починати з sequential execution (паралельні steps — як TODO на майбутнє)
- Template engine — мінімальний, без логіки (no if/for/filters крім default)
- Максимум 20 steps per workflow
- Максимальний timeout workflow: 10 хвилин
- YAML definitions зберігаються в `config/workflows/` (опціонально, основне — через DB)
