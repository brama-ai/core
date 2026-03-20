---
name: summarizer
description: "Summarizer role: final pipeline summary format, Ukrainian output"
---

## Summary Format

Write in **Ukrainian**. File: `builder/tasks/summary/<timestamp>-<slug>.md`

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
10. `## Наступна задача`

For the telemetry sections, prefer pasting the helper output verbatim and only adjust surrounding narrative text.

## Model Tracking

Agents may use fallback models when primary hits rate limits. The `model` field in checkpoint.json and meta.json shows the **actual model that completed the work**, not the configured primary. Always report the actual model in the summary table — this is critical for cost tracking and debugging.

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
