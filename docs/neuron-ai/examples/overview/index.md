# Neuron AI Examples

Reference projects demonstrating key Neuron AI capabilities.
Run `composer install` in this directory to fetch all example source code.

## Projects

| Project | Topics | Source |
|---------|--------|--------|
| [neuron-ai](https://github.com/neuron-core/neuron-ai) | Framework core, agents, tools, RAG, workflows | `vendor/neuron-core/neuron-ai` |
| [a2a](https://github.com/neuron-core/a2a) | A2A protocol server, agent-to-agent communication | `vendor/neuron-core/a2a` |
| [laravel-travel-agent](https://github.com/neuron-core/laravel-travel-agent) | Multi-agent orchestration, Laravel + Livewire UI | `vendor/neuron-core/laravel-travel-agent` |
| [travel-planner-agent](https://github.com/neuron-core/travel-planner-agent) | Workflow interrupts, tool injection, CLI agent | `vendor/neuron-core/travel-planner-agent` |
| [youtube-ai-agent](https://github.com/neuron-core/youtube-ai-agent) | Simple single-agent, custom tool, streaming | `vendor/neuron-core/youtube-ai-agent` |
| [deep-research-agent](https://github.com/neuron-core/deep-research-agent) | Nested workflows, loop nodes, deep research | `vendor/neuron-core/deep-research-agent` |

---

## 1. Multi-Agent Orchestration

**Best example:** `laravel-travel-agent` → `app/Neuron/`

Workflow з 6 нодами-агентами в послідовному pipeline:

```
Receptionist → Delegator → Flights/Hotels/Places → GenerateItinerary
```

**Ключові файли:**
- `TravelPlannerAgent.php` — extends `Workflow`, координує ноди
- `Nodes/Receptionist.php` — збирає дані від юзера через structured output
- `Nodes/RetrieveDelegator.php` — роутер, який направляє до потрібного ноду за стейтом
- `Nodes/GenerateItinerary.php` — фінальний нод, синтезує результат зі streaming

**Патерн Delegator (роутер):**
```php
// Nodes/RetrieveDelegator.php
public function __invoke(Retrieve $event, WorkflowState $state): Generator|RetrieveFlights|RetrieveHotels|RetrievePlaces|CreateItinerary
{
    if (!$state->has('flights')) return new RetrieveFlights($event->tourInfo);
    if (!$state->has('hotels'))  return new RetrieveHotels($event->tourInfo);
    if (!$state->has('places'))  return new RetrievePlaces($event->tourInfo);
    return new CreateItinerary();
}
```

**Dynamic tool injection per node:**
```php
// Nodes/Flights.php — додає tool в рантаймі, а не при створенні агента
$agent = ResearchAgent::make()->addTool(SerpAPIFlight::make());
$response = $agent->chat(new UserMessage($prompt));
```

---

## 2. Nested Workflows & Loop Pattern

**Best example:** `deep-research-agent` → `src/`

Вкладені воркфлоу для глибокого дослідження:

```
Planning → [GenerateSectionContent ↻ loop] → Format
                    ↓
            SearchWorkflow (nested)
            GenerateQueries → SearchTheWeb
```

**Ключові файли:**
- `DeepResearchAgent.php` — головний workflow
- `SearchWorkflow.php` — **вкладений workflow**, створюється всередині ноду
- `Nodes/GenerateSectionContent.php` — **loop pattern**: повертає той самий event поки не опрацює всі секції

**Loop-until-complete pattern:**
```php
// Nodes/GenerateSectionContent.php
$current = $state->get('current_section', 0);
// ... process section ...
$state->set('current_section', $current + 1);

if ($current + 1 < count($sections)) {
    return new SectionGenerationEvent(); // loop — re-invoke self
}
return new FormattingReportEvent(); // done — move to next node
```

**Nested workflow:**
```php
// Всередині ноду створюється окремий workflow для пошуку
$searchWorkflow = new SearchWorkflow($state);
$handler = $searchWorkflow->start();
$result = $handler->getResult();
```

---

## 3. A2A Protocol — правильна реалізація

**Best example:** `a2a` → `src/`

Три контракти для створення A2A сервера:

| Interface | Відповідальність |
|-----------|-----------------|
| `TaskRepositoryInterface` | Зберігання тасків (in-memory, DB, Redis) |
| `MessageHandlerInterface` | Логіка AI агента |
| `AgentCardProviderInterface` | Опис можливостей агента |

**Ключові файли:**
- `Server/A2AServer.php` — abstract, обробляє JSON-RPC 2.0 запити
- `Model/AgentCard/AgentCard.php` — agent card з skills, capabilities
- `Model/Task.php` — task lifecycle з `TaskState` enum
- `Laravel/A2A.php` — роутер для Laravel (`A2A::route('/a2a/agent', Server::class)`)

**Task Lifecycle:**
```
QUEUED → RUNNING → COMPLETED
                 → INPUT_REQUIRED (multi-turn)
                 → FAILED / CANCELED / REJECTED
```

**JSON-RPC методи:**
- `message/send` — надіслати повідомлення агенту (створює або продовжує task)
- `tasks/get` — отримати task за ID
- `tasks/list` — список тасків (фільтр по contextId, пагінація)
- `tasks/cancel` — скасувати task

**Laravel scaffolding command:**
```bash
php artisan make:a2a DataAnalyst
# Генерує: Server, TaskRepository, MessageHandler, AgentCard
```

**MessageHandler — точка інтеграції з Neuron AI:**
```php
class MyMessageHandler implements MessageHandlerInterface {
    public function handle(Task $task, array $messages): Task {
        $agent = MyAgent::make();
        $text = $messages[0]->parts[0]->text;
        $response = $agent->chat(new UserMessage($text));
        // ... update task with response ...
        return $task;
    }
}
```

---

## 4. Workflow Interrupts (Human-in-the-Loop)

**Best example:** `travel-planner-agent` → `src/Nodes/Receptionist.php`

```php
// Нод зупиняє workflow і питає юзера
if (!$tourInfo->isComplete()) {
    $this->interrupt(['question' => $response->description]);
}

// Відновлення з input від юзера
$feedback = $this->consumeInterruptFeedback();
```

**Виклик з CLI:**
```php
try {
    $handler = $workflow->start();
} catch (WorkflowInterrupt $e) {
    $userInput = readline($e->getData()['question']);
    $handler = $workflow->wakeup($userInput);
}
```

---

## 5. Structured Output з валідацією

**Best example:** `deep-research-agent` → `src/Agents/`

```php
// DTO з атрибутами — генерує JSON Schema автоматично
class ReportSection {
    #[SchemaProperty(description: 'Section name')]
    public string $name;

    #[SchemaProperty(description: 'Section description')]
    public string $description;
}

// Виклик — LLM повертає типізований PHP об'єкт
$plan = $agent->structured($prompt, ReportPlanOutput::class);
```

Підтримує: nested objects, enums, arrays з типами, union types, validation attributes (`#[Length]`, `#[Email]`).

---

## 6. Streaming + Real-time UI

**Best example:** `laravel-travel-agent` → `app/Livewire/Chat.php`

**Backend — yield progress з нодів:**
```php
// В будь-якому ноді
yield new ProgressEvent('Searching for flights...');
```

**Frontend — Livewire streaming:**
```php
// Chat.php Livewire component
foreach ($handler->streamEvents() as $event) {
    if ($event instanceof ProgressEvent) {
        $this->stream('response', $event->message);
    }
}
```

```html
<!-- Blade template -->
<div wire:stream="response">{!! Str::markdown($content) !!}</div>
```

---

## 7. Simple Agent (без workflow)

**Best example:** `youtube-ai-agent` → `src/Agents/`

Мінімальний агент — один клас, один tool:

```php
class YouTubeAgent extends Agent {
    protected function provider(): AIProviderInterface {
        return new Anthropic($_ENV['ANTHROPIC_API_KEY'], 'claude-3-7-sonnet-latest');
    }
    protected function instructions(): string {
        return 'You are a YouTube video analyst...';
    }
    protected function tools(): array {
        return [GetTranscriptionTool::make()];
    }
}
```

---

## Architectural Patterns Summary

| Pattern | Де знайти | Коли використовувати |
|---------|-----------|---------------------|
| Single agent + tools | youtube-ai-agent | Прості задачі з 1-3 tools |
| Workflow pipeline | travel-planner-agent | Послідовні кроки з різними агентами |
| Delegator/router node | laravel-travel-agent | Динамічне розгалуження за стейтом |
| Loop node | deep-research-agent | Ітеративна обробка колекцій |
| Nested workflow | deep-research-agent | Складна підзадача всередині ноду |
| Workflow interrupt | travel-planner-agent | Потрібен input від юзера посередині |
| Structured output | deep-research-agent | Типізовані відповіді від LLM |
| Dynamic tool injection | travel-planner-agent | Різні tools для різних нодів |
| A2A server | a2a package | Agent-to-agent комунікація по протоколу |
| Streaming + Livewire | laravel-travel-agent | Real-time UI оновлення |

---

## Setup

```bash
cd docs/neuron-ai/examples
composer install
```

Після install — код у `vendor/neuron-core/`. Кожен проект має свій README.
