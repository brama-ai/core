---
name: summarizer
description: "Summarizer role: final pipeline summary format, Ukrainian output"
---

## Summary Format

Write in **Ukrainian**. File naming convention:
- **Builder** workflow: `builder/tasks/summary/b-<timestamp>-<slug>.md`
- **Ultraworks** workflow: `builder/tasks/summary/u-<timestamp>-<slug>.md`

Determine the workflow from handoff.md (`**Workflow:**` field) or from the pipeline context.

```markdown
# <Назва задачі>

**Статус:** PASS | FAIL
**Workflow:** Builder | Ultraworks
**Профіль:** <profile name>
**Тривалість:** Xm Ys

## Що зроблено
- Стислі bullet points що саме реалізовано
- Файли створені/змінені (кількість)
- Міграції (якщо є)

## Telemetry
| Agent | Model | Input | Output | Price | Time |
|-------|-------|------:|-------:|------:|-----:|
| coder | anthropic/claude-sonnet-4-6 | 45210 | 11840 | $0.12 | 3m 12s |

## Моделі
| Model | Agents | Input | Output | Price |
|-------|--------|------:|-------:|------:|
| anthropic/claude-sonnet-4-6 | coder,tester | 81234 | 21004 | $0.21 |

## Tools By Agent
### coder
- `read` x 8
- `grep` x 3

## Files Read By Agent
### coder
- `builder/pipeline.sh`
- `.opencode/skills/summarizer/SKILL.md`

## Труднощі
- Проблеми які виникли та як вирішені

## Незавершене
- Що лишилось зробити (якщо є)

## Рекомендації по оптимізації
> Ця секція ОБОВ'ЯЗКОВА якщо виконується будь-яка з умов нижче. Інакше — не додавати.

(див. Anomaly Detection Rules нижче)

## Рекомендовані задачі
> Зібрані з `## Recommended follow-up tasks` у handoff.md — пропозиції від агентів
> які помітили роботу поза їхнім scope. Кожна — окрема задача для pipeline.

- **[Назва]** — [чому потрібно], зачіпає [файли/область]

## Наступна задача
Одна конкретна пропозиція що робити далі.
```

## Data Sources

| Source | Path | What to extract |
|--------|------|-----------------|
| Handoff | `.opencode/pipeline/handoff.md` | What each agent did, verdicts |
| Checkpoint | `builder/tasks/artifacts/<slug>/checkpoint.json` | **Actual model used** (may differ from primary due to fallback), status, duration, tokens |
| Meta files | `.opencode/pipeline/logs/*_*.meta.json` | Tokens, cost, duration, **actual model** |
| Telemetry | `builder/tasks/artifacts/<slug>/telemetry/*.json` | Tools, files read, actual cost per agent |
| Plan | `pipeline-plan.json` | Profile, reasoning, apps |
| Audit reports | `.opencode/pipeline/reports/*_audit.md` | Verdict, findings |

## Required Commands

Prefer generating the telemetry block via the helper script instead of hand-building the tables.

### Builder

```bash
builder/cost-tracker.sh summary-block --workflow builder --task-slug "<slug>"
```

### Ultraworks

```bash
builder/cost-tracker.sh summary-block --workflow ultraworks
```

If the helper finds no telemetry for a section, preserve the section header and print `- none recorded`.

## Output Contract

The final summary MUST contain both:
- a short human narrative
- the full telemetry block

Required section order:
1. Title
2. Status / Workflow / Profile / Duration
3. `## Що зроблено`
4. `## Telemetry`
5. `## Моделі`
6. `## Tools By Agent`
7. `## Files Read By Agent`
8. `## Труднощі`
9. `## Незавершене`
10. `## Рекомендації по оптимізації` (**only if anomaly detected** — see rules below)
11. `## Рекомендовані задачі` (collect from `## Recommended follow-up tasks` in handoff.md — omit if none)
12. `## Наступна задача`

For the telemetry sections, prefer pasting the helper output verbatim and only adjust surrounding narrative text.

## Model Tracking

Agents may use fallback models when primary hits rate limits. The `model` field in checkpoint.json and meta.json shows the **actual model that completed the work**, not the configured primary. Always report the actual model in the summary table — this is critical for cost tracking and debugging.

## Anomaly Detection & Optimization Recommendations

The `## Рекомендації по оптимізації` section is **MANDATORY** when ANY of these anomalies is detected:

### Triggers (check each one)

| Anomaly | Threshold | What to recommend |
|---------|-----------|-------------------|
| **Agent failed** | Any agent status = failed/error/timeout | Root cause + how to prevent (model choice? prompt? infra?) |
| **Pipeline incomplete** | Status = FAIL or any phase pending/interrupted | Which phase broke the chain + what to fix in workflow |
| **Token anomaly** | Any single agent > 500K input tokens | Why so many reads? Suggest scoping, caching, or splitting task |
| **Total cost anomaly** | Pipeline total > $2.00 | Which agent burned most + suggest cheaper model or narrower scope |
| **Duration anomaly** | Any single agent > 15 minutes | What took so long? PHPStan? Tests? Network? Suggest fast model or timeout |
| **Retry storm** | Same agent retried 3+ times | What keeps failing? Suggest prompt fix, model change, or task split |
| **Fallback cascade** | Agent fell through to 3+ fallback models | Primary + fallbacks unavailable — suggest adding providers or checking API keys |
| **Empty output** | Agent ran but produced 0 file changes | Did agent misunderstand the task? Suggest clearer delegation prompt |

### Recommendation Format

```markdown
## Рекомендації по оптимізації

### 🔴 [Anomaly type]: [brief description]
**Що сталось:** [what went wrong]
**Вплив:** [impact on pipeline — time/cost/quality]
**Рекомендація:** [concrete fix]
- Варіант A: [specific action]
- Варіант B: [alternative]

### 🟡 [Another anomaly if any]
...
```

Use 🔴 for blocking issues (failed/incomplete), 🟡 for warnings (slow/expensive but succeeded).

### Where to look for data

- **Token counts**: telemetry files, meta.json, checkpoint.json
- **Duration**: checkpoint.json `duration` field, or calculate from timestamps in handoff
- **Failures**: handoff.md phase statuses, log files in `.opencode/pipeline/logs/`
- **Retries**: count how many times same agent appears in logs
- **Fallbacks**: check `model` field in meta.json vs configured primary in oh-my-opencode.jsonc

## Rules

- Include only agents that actually ran
- Always include `**Workflow:** ...`
- Always include `## Що зроблено`, `## Труднощі`, `## Незавершене`, and `## Наступна задача`
- End with exactly one follow-up task proposal
- Mark: **PIPELINE COMPLETE** or **PIPELINE INCOMPLETE** (with remaining items)
- Always write the summary file even if upstream phases failed
- If the pipeline failed, set `**Статус:** FAIL` and describe partial progress plus blocking issue
- Keep it concise outside the telemetry tables — this is for human review

## References (load on demand)

| What | Path | When |
|------|------|------|
| Handoff bus | `.opencode/pipeline/handoff.md` | Always — primary data |
| Pipeline plan | `pipeline-plan.json` | If exists |
| Task file | `builder/tasks/in-progress/*.md` or `done/*.md` | Original task description |
