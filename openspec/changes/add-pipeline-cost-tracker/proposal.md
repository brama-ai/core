# Proposal: add-pipeline-cost-tracker

## Summary

Додати модуль підрахунку приблизної вартості використання AI провайдерів (Anthropic, OpenAI, Google, OpenRouter) в pipeline runtime і розширити summary до формату execution telemetry report. Report повинен працювати однаково для `Builder` і `Ultraworks`, показувати per-agent і per-model usage, приблизну вартість, інструменти, що викликались, список файлів, які були прочитані кожним агентом, і явно вказувати, який workflow виконав задачу.

## Motivation

Pipeline workflows витрачають токени через кілька провайдерів (Anthropic Claude, OpenAI Codex, Google Gemini, OpenRouter). Зараз немає способу відстежувати:
- Скільки коштує кожна задача, кожен агент і кожна фактична модель
- Який % підписки/бюджету вже використано
- Які tools реально викликав агент під час виконання
- Які файли агент читав для виконання задачі
- Який саме workflow (`Builder` чи `Ultraworks`) виконав конкретний report
- Коли pipeline наближається до rate limit

## Scope

### In scope
- Bash-модуль `builder/cost-tracker.sh` з функціями підрахунку
- ENV конфігурація тарифного плану (`PIPELINE_PROVIDER_*` змінні)
- `.env.local.example` з коментарями по тарифних планах
- Інтеграція в `builder` і `ultraworks` execution flow (emit cost events) та monitor Activity tab
- Підрахунок на основі token counts з `.meta.json` × pricing per 1M tokens
- Per-agent telemetry sidecar для tools, files-read, duration, actual model
- Structured summary table in `builder/tasks/summary/*.md`:
  - `agent`, `model`, `input`, `output`, `price`, `time`
- Workflow identifier in `summary.md`
- Per-model token aggregation block in `summary.md`
- Per-agent tools section in `summary.md`
- Per-agent files-read section in `summary.md`

### Out of scope
- Реальне API до провайдерів для перевірки лімітів (їх немає)
- Точний підрахунок (тільки приблизний, на основі публічних цін)
- Billing інтеграції чи платіжні системи
- Full provenance for shell-level reads outside the agent runtime if the runtime cannot expose them

## Design

Модуль буде standalone bash script `builder/cost-tracker.sh` який:
1. Читає pricing config з ENV (`PIPELINE_PLAN_ANTHROPIC`, `PIPELINE_PLAN_OPENAI`, etc.)
2. Парсить `.meta.json` файли з поточного batch незалежно від workflow
3. Розраховує cost per agent step: `(input_tokens × input_price + output_tokens × output_price + cache_read × cache_price) / 1_000_000`
4. Агрегує total cost per provider та % від ліміту підписки
5. Зберігає/агрегує telemetry sidecars з фактичними tools і files-read по агенту
6. Експортує результати для monitor через events.log і для `summary.md` через structured task artifacts
7. Зберігає workflow label (`builder` або `ultraworks`) як частину telemetry/report metadata

## Dependencies

- Існуючі `.meta.json` файли (вже є, мають `tokens.input_tokens`, `tokens.output_tokens`, `tokens.cache_read`, `tokens.cost`)
- `builder/pipeline.sh` (emit events)
- `builder/monitor/pipeline-monitor.sh` (display)
- `.opencode/skills/summarizer/SKILL.md` і summary writer flow
- `Ultraworks` orchestration path, якщо він пише окремі runtime artifacts
