# Tasks: add-pipeline-cost-tracker

## 1. Cost Tracker Module
- [ ] 1.1 Create `builder/cost-tracker.sh` with pricing data for all known models (Anthropic Opus/Sonnet/Haiku, OpenAI GPT-5/Codex/GPT-5.4, Google Gemini Pro/Flash, OpenRouter markup)
- [ ] 1.2 Implement `detect_pricing_tier()` — maps model string from `.meta.json` to pricing tuple (input/output/cache per 1M tokens)
- [ ] 1.3 Implement `calculate_step_cost()` — reads `.meta.json`, applies pricing, returns USD cost
- [ ] 1.4 Implement `aggregate_daily_usage()` — scans all `.meta.json` from today across worktrees and main logs, sums per provider
- [ ] 1.5 Implement `get_daily_budget()` — reads plan type from ENV, returns daily budget per provider
- [ ] 1.6 Implement `format_usage_line()` — formats per-provider spend as "Provider: $X.XX/~$Y.YY (Z%)" with color thresholds (green/yellow/red)

## 2. ENV Configuration
- [ ] 2.1 Add pipeline plan variables to `.env.local.example` with commented pricing tiers:
  - `PIPELINE_PLAN_ANTHROPIC` (free, pro=$20, max5x=$100, max20x=$200, api=pay-per-token)
  - `PIPELINE_PLAN_OPENAI` (free, plus=$20, pro=$200, api=pay-per-token)
  - `PIPELINE_PLAN_GOOGLE` (free, pro=$20, ultra=$42, api=pay-per-token)
  - `PIPELINE_PLAN_OPENROUTER_BUDGET` (prepaid balance in USD, default 0)
- [ ] 2.2 Add default plan values to `builder/cost-tracker.sh` (fallback when ENV not set)

## 3. Pipeline Integration
- [ ] 3.1 Source `builder/cost-tracker.sh` in `builder/pipeline.sh`
- [ ] 3.2 After each agent completes (in `run_agent`), call `calculate_step_cost` and emit `COST` event to `events.log`
- [ ] 3.3 At pipeline end, call `aggregate_daily_usage` and emit summary `COST_SUMMARY` event
- [ ] 3.4 Include step cost in `_build_task_meta()` output (pipeline-batch.sh task footer)
- [ ] 3.5 Persist per-agent telemetry sidecars with actual model, tokens, duration, tools, and files-read
- [ ] 3.6 Normalize runtime/tool logs into a stable telemetry schema consumable by the summarizer
- [ ] 3.7 Apply the same telemetry schema and summary inputs to the `Ultraworks` flow
- [ ] 3.8 Persist workflow identity (`builder` / `ultraworks`) in task telemetry metadata

## 4. Monitor Integration
- [ ] 4.1 In `render_logs_tab()`, add footer section that reads `COST` and `COST_SUMMARY` events from `events.log`
- [ ] 4.2 Render per-provider usage line with color coding (green <70%, yellow 70-90%, red >90%)
- [ ] 4.3 Show cost per agent step in event lines (append `$0.42` after token info)

## 5. Summary Integration
- [ ] 5.1 Update `.opencode/skills/summarizer/SKILL.md` to require telemetry tables and per-agent tool/files sections
- [ ] 5.2 Render summary header with workflow label
- [ ] 5.3 Render summary agent table with columns: `Agent`, `Model`, `Input`, `Output`, `Price`, `Time`
- [ ] 5.4 Render summary model totals table with grouped input/output/price by actual model
- [ ] 5.5 Render "Tools by agent" section from telemetry sidecars
- [ ] 5.6 Render "Files read by agent" section from telemetry sidecars
- [ ] 5.7 Ensure one summary renderer works for both `Builder` and `Ultraworks` tasks

## 6. Quality
- [ ] 6.1 `bash -n builder/cost-tracker.sh` passes (valid syntax)
- [ ] 6.2 `bash -n builder/pipeline.sh` passes
- [ ] 6.3 `bash -n builder/monitor/pipeline-monitor.sh` passes
- [ ] 6.4 Manual test: run one pipeline task, verify cost events appear in events.log
- [ ] 6.5 Manual test: verify summary includes workflow label, telemetry tables, tools, and files-read sections
- [ ] 6.6 Manual test: verify `Builder` and `Ultraworks` both produce the same summary structure
- [ ] 6.7 Manual test: verify monitor Activity tab shows cost footer

## 7. Documentation
- [ ] 7.1 Add cost tracking and summary telemetry section to `builder/AGENTS.md`
- [ ] 7.2 Add inline comments in `cost-tracker.sh` explaining pricing sources and update procedure
- [ ] 7.3 Update pipeline/summary docs under `docs/` to describe the new summary tables and telemetry provenance
