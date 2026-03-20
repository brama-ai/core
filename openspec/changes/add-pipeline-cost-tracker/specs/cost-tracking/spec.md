## ADDED Requirements

### Requirement: Cost Tracker Module
The pipeline SHALL include a `builder/cost-tracker.sh` module that calculates approximate cost of AI provider usage based on token counts from `.meta.json` files and known pricing per model.

#### Scenario: Calculate cost for a completed agent step
- **GIVEN** a `.meta.json` file with `tokens.input_tokens`, `tokens.output_tokens`, `tokens.cache_read` and `model` field
- **WHEN** `calculate_step_cost` is called with the meta file path
- **THEN** it returns the estimated cost in USD based on the model's pricing tier
- **AND** the formula is: `(input × input_price + output × output_price + cache_read × cache_price) / 1_000_000`

#### Scenario: Detect provider and model tier from model string
- **GIVEN** a model string like `anthropic/claude-opus-4-20250514`
- **WHEN** `detect_pricing_tier` is called
- **THEN** it returns the correct pricing tuple (input/output/cache per 1M tokens)

#### Scenario: Unknown model falls back to zero cost
- **GIVEN** a model string that doesn't match any known pricing
- **WHEN** `detect_pricing_tier` is called
- **THEN** it returns `0:0:0` (no charge assumed)

---

### Requirement: Per-Agent Telemetry Capture
The pipeline SHALL persist structured per-agent telemetry that records the actual model used, token counts, duration, invoked tools, and files read during the step.

#### Scenario: Persist telemetry for a completed agent step
- **GIVEN** an agent step completes and its `.meta.json` exists
- **WHEN** the pipeline finalizes that step
- **THEN** it writes a telemetry record for the same step containing `agent`, `model`, `tokens`, `duration_ms`, `tools`, and `files_read`

#### Scenario: Use actual fallback model in telemetry
- **GIVEN** an agent falls back from its configured primary to another model
- **WHEN** telemetry is recorded
- **THEN** the `model` field reflects the actual model that completed the step
- **AND** summary aggregation uses that actual model

#### Scenario: Deduplicate repeated file reads within a step
- **GIVEN** an agent reads the same file multiple times in one step
- **WHEN** the telemetry record is normalized
- **THEN** the file appears once in `files_read`
- **AND** tool counts still preserve repeated tool usage

#### Scenario: Record workflow identity with telemetry
- **GIVEN** a task runs through either the `Builder` or `Ultraworks` workflow
- **WHEN** telemetry is persisted for any agent step
- **THEN** the record includes the originating workflow identifier

---

### Requirement: Workflow-Agnostic Reporting
The pipeline reporting contract SHALL support both `Builder` and `Ultraworks` using the same telemetry schema and summary structure.

#### Scenario: Builder task renders report
- **GIVEN** a task executed through the `Builder` workflow
- **WHEN** the summary is generated
- **THEN** it uses the shared telemetry schema and shared summary sections

#### Scenario: Ultraworks task renders report
- **GIVEN** a task executed through the `Ultraworks` workflow
- **WHEN** the summary is generated
- **THEN** it uses the same telemetry schema and the same summary sections as `Builder`

---

### Requirement: Summary Agent Cost Table
The pipeline summary SHALL include a structured per-agent execution table with the columns `Agent`, `Model`, `Input`, `Output`, `Price`, and `Time`.

#### Scenario: Render agent execution row
- **GIVEN** a completed agent telemetry record with token and duration data
- **WHEN** the summary is generated
- **THEN** the summary table includes one row for that agent step
- **AND** `Input` and `Output` are sourced from the actual token counts
- **AND** `Price` is the estimated USD cost for that step

#### Scenario: Render failed agent row
- **GIVEN** an agent step failed after producing telemetry
- **WHEN** the summary is generated
- **THEN** the step still appears in the table with its available token, price, and time data

---

### Requirement: Summary Workflow Header
The pipeline summary SHALL identify which workflow produced the report.

#### Scenario: Render builder workflow header
- **GIVEN** telemetry metadata declares the workflow as `builder`
- **WHEN** the summary is generated
- **THEN** the summary header includes `Workflow: Builder`

#### Scenario: Render ultraworks workflow header
- **GIVEN** telemetry metadata declares the workflow as `ultraworks`
- **WHEN** the summary is generated
- **THEN** the summary header includes `Workflow: Ultraworks`

---

### Requirement: Summary Model Aggregation
The pipeline summary SHALL include aggregated token and cost totals grouped by actual model.

#### Scenario: Aggregate usage across agents sharing a model
- **GIVEN** multiple agent steps completed with the same actual model
- **WHEN** the summary is generated
- **THEN** the model totals table sums their input tokens, output tokens, and price into one row for that model

#### Scenario: Separate fallback model totals
- **GIVEN** one agent ran on its fallback model while another used the configured primary
- **WHEN** the summary is generated
- **THEN** each actual model appears in its own aggregation row

---

### Requirement: Summary Tool Breakdown
The pipeline summary SHALL list the tools invoked by each agent below the execution tables.

#### Scenario: Render per-agent tool list
- **GIVEN** a telemetry record contains tool usage counts
- **WHEN** the summary is generated
- **THEN** the summary includes a section for that agent listing each tool and its count

#### Scenario: No tools recorded
- **GIVEN** a telemetry record contains no tool usage
- **WHEN** the summary is generated
- **THEN** the agent's tools section includes an explicit placeholder indicating that no tools were recorded

---

### Requirement: Summary Files-Read Breakdown
The pipeline summary SHALL list the files read by each agent below the tool breakdown.

#### Scenario: Render files read per agent
- **GIVEN** a telemetry record contains a `files_read` list
- **WHEN** the summary is generated
- **THEN** the summary includes a section for that agent listing those files

#### Scenario: No files recorded
- **GIVEN** a telemetry record contains no readable file list
- **WHEN** the summary is generated
- **THEN** the agent's files section includes an explicit placeholder indicating that no files were recorded

---

### Requirement: Subscription Plan Configuration
The pipeline SHALL read subscription plan type from ENV variables and calculate daily budget accordingly.

#### Scenario: Configure Anthropic subscription plan
- **GIVEN** `PIPELINE_PLAN_ANTHROPIC=max5x` in `.env.local`
- **WHEN** the cost tracker initializes
- **THEN** it sets the monthly budget to $100 and daily budget to ~$3.33

#### Scenario: Configure OpenAI subscription plan
- **GIVEN** `PIPELINE_PLAN_OPENAI=plus` in `.env.local`
- **WHEN** the cost tracker initializes
- **THEN** it sets the monthly budget to $20 and daily budget to ~$0.67

#### Scenario: API-only mode (no subscription limit)
- **GIVEN** `PIPELINE_PLAN_ANTHROPIC=api` in `.env.local`
- **WHEN** the cost tracker calculates usage percentage
- **THEN** it shows cost but no percentage (unlimited pay-per-token)

---

### Requirement: Daily Usage Aggregation
The pipeline SHALL aggregate costs from all `.meta.json` files created today and compare against daily budget.

#### Scenario: Aggregate today's usage across multiple tasks
- **GIVEN** multiple `.meta.json` files from today's pipeline runs
- **WHEN** `aggregate_daily_usage` is called
- **THEN** it sums costs per provider and returns total per provider with percentage of daily budget

#### Scenario: Color-coded usage thresholds
- **GIVEN** daily usage percentage for a provider
- **WHEN** the monitor displays the cost bar
- **THEN** 0-70% is shown in green, 70-90% in yellow, 90%+ in red

---

### Requirement: Pipeline Integration
The pipeline SHALL emit cost events after each agent completes and display aggregated costs in the monitor Activity tab.

#### Scenario: Emit cost event after agent completion
- **WHEN** an agent completes and its `.meta.json` is written
- **THEN** pipeline.sh emits a `COST` event to `events.log` with agent, provider, model, step cost, and daily usage percentage

#### Scenario: Display cost summary in Activity tab footer
- **WHEN** the monitor renders the Activity tab
- **THEN** it shows a footer line with per-provider daily spend and percentage (e.g., `Anthropic: $1.23/~$3.33 (37%)`)

---

### Requirement: ENV Configuration Template
The `.env.local.example` SHALL document all available plan options with pricing comments.

#### Scenario: Plan options documented in .env.local.example
- **GIVEN** a new developer reads `.env.local.example`
- **THEN** they see commented sections for each provider with available plans and their monthly prices
- **AND** default values are set to the most common free/cheap options
