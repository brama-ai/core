# Agent PRD Template

## 1. Summary

- Agent name:
- Problem it solves:
- Main user:

## 2. Goal

Що саме агент повинен дати ком'юніті в межах MVP.

## 3. Users and Jobs-to-be-Done

- Admin:
- Moderator:
- Member:

## 4. Scope

### In Scope

- ...

### Out of Scope

- ...

## 5. Inputs

- події
- команди
- дані зі shared storage
- LLM prompt/context через platform-owned `LiteLLM` proxy (якщо агент використовує LLM)

## 6. Outputs

- відповіді в чат
- записи в БД
- сигнали або дії для модераторів

## 7. UX / Commands

- `/command`
- `/command action`

## 8. Data Model Usage

- які таблиці читає
- які таблиці пише

## 9. Rules / Heuristics

- базова логіка
- пороги
- обмеження

## 10. LLM Usage (If Applicable)

- чи потрібен LLM взагалі
- усі LLM-запити йдуть через `LiteLLM`, а не напряму до provider API / SDK
- які model aliases використовує агент
- які дані потрапляють у prompt/context
- які дані не можна відправляти в prompt
- timeout, retry, max tokens / budget
- fallback, якщо `LiteLLM` або provider недоступний

## 11. Failure Modes

- що робимо при помилці
- що бачить користувач
- як логуються помилки

## 12. Success Metrics

- adoption
- accuracy / usefulness
- moderator feedback
