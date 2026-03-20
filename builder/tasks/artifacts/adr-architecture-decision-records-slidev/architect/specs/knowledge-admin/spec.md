## ADDED Requirements

### Requirement: Encyclopedia Visibility Toggle
The admin panel SHALL include a settings page with a toggle to enable or disable the public web encyclopedia.

#### Scenario: Admin disables encyclopedia
- **WHEN** admin switches the "Веб-енциклопедія" toggle to OFF and saves
- **THEN** the `/wiki` endpoint returns `503` and the setting is persisted

#### Scenario: Admin enables encyclopedia
- **WHEN** admin switches the toggle to ON and saves
- **THEN** the `/wiki` endpoint becomes publicly accessible again

---

### Requirement: Base Agent Instructions Editor
The admin panel SHALL provide a textarea-based editor for the KnowledgeBaseAgent's base system instructions, which are prepended to every LLM prompt.

#### Scenario: Admin saves new base instructions
- **WHEN** admin edits the base instructions textarea and clicks "Зберегти"
- **THEN** the new instructions are persisted and used in the next extraction workflow run

#### Scenario: Instructions empty validation
- **WHEN** admin saves an empty base instructions field
- **THEN** the system shows a validation error: "Базові інструкції не можуть бути порожніми"

---

### Requirement: Security Instructions (Always Appended)
The admin panel SHALL display read-only security instructions that are always appended to every LLM prompt, regardless of compaction or context changes.

#### Scenario: Security instructions always included
- **WHEN** any extraction workflow run executes
- **THEN** the LLM prompt always contains the security instructions as the last block, after base instructions and any dynamic context

#### Scenario: Security instructions not editable by admin
- **WHEN** admin views the agent instructions page
- **THEN** the security instructions section is displayed in a read-only locked area with a visual indicator

---

### Requirement: Instruction Preview Interface
The admin panel SHALL include a chat-like interface for previewing how the agent interprets a given instruction set, without affecting live extraction.

#### Scenario: Admin sends test message in preview
- **WHEN** admin types a sample message chunk in the preview interface and clicks "Перевірити"
- **THEN** the interface shows the agent's interpreted extraction result (preview mode, no data stored)

#### Scenario: Preview mode label visible
- **WHEN** the preview interface is active
- **THEN** a prominent label "Режим перегляду — дані не зберігаються" is displayed

---

### Requirement: Knowledge CRUD Admin Page
The admin panel SHALL provide a knowledge management page with the same tree-based navigation as the web encyclopedia, plus full CRUD operations on entries.

#### Scenario: Admin views entries in tree
- **WHEN** admin navigates to `/admin/knowledge`
- **THEN** the left panel shows the knowledge tree and the center panel shows the entry list for the selected node

#### Scenario: Admin creates new entry manually
- **WHEN** admin clicks "Додати знання" and fills in title, body, category, tags, then saves
- **THEN** a new entry is created via the CRUD API and appears in the tree

#### Scenario: Admin edits existing entry
- **WHEN** admin clicks "Редагувати" on an entry, modifies the body, and saves
- **THEN** the entry is updated and the embedding is regenerated

#### Scenario: Admin deletes entry with confirmation
- **WHEN** admin clicks "Видалити" and confirms the prompt
- **THEN** the entry is removed from OpenSearch and the tree node count is updated
