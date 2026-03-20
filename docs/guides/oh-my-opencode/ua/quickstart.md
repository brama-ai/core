# Oh My OpenCode — Швидкий старт

## Передумови

Devcontainer встановлює все автоматично. Якщо вручну:
```bash
npm install -g opencode-ai intelephense
bunx oh-my-opencode install --no-tui --claude=max5 --gemini=no --copilot=no
make builder-setup
```

Перевірка: `opencode --version` (1.0.150+)

---

## Сценарій 1: Проста задача (однією фразою)

Швидкий фікс або невелика фіча. Просто напишіть в OpenCode:

```
ultrawork Fix the health endpoint to return proper Content-Type header in hello-agent
```

**Що відбувається:**
1. Sisyphus читає handoff.md, вирішує пропустити architect (спека не потрібна)
2. Делегує s-coder → пише фікс
3. Делегує s-validator + s-tester паралельно → лінт + тести
4. Делегує s-summarizer → пише summary

**Результат:** Гілка `pipeline/fix-hello-health-content-type`, summary в `builder/tasks/summary/`.

### Інші швидкі приклади

```
ultrawork Add missing PHPStan return types in knowledge-agent
```
```
ultrawork Update the admin dashboard sidebar to include coder link
```
```
/validate                    # просто лінт + тести на поточних змінах
```

---

## Сценарій 2: OpenSpec пропозиція (планована фіча)

Реальна фіча що потребує spec-first проектування. Два кроки:

### Крок 1 — Створити пропозицію

В OpenCode переключіться на architect або використайте Sisyphus:

```
/auto Create OpenSpec proposal for adding webhook notifications to agent lifecycle events
```

Sisyphus делегує s-architect який створює:
```
openspec/changes/add-webhook-notifications/
├── proposal.md          # що і навіщо
├── design.md            # архітектура, компроміси
├── tasks.md             # впорядковані задачі з чекбоксами
└── specs/
    └── webhooks/spec.md # spec deltas зі сценаріями
```

### Крок 2 — Реалізація

Перегляньте пропозицію, потім:

```
/implement add-webhook-notifications
```

Це пропускає Фазу 1 (architect) і запускає Фази 2-5:
coder → validator ∥ tester → audit loop (якщо агент) → docs ∥ summary

### Крок 3 — Відновити якщо перервано

Якщо щось зафейлилось або було перервано:

```
/finish
```

Читає handoff.md, визначає що зроблено, запускає тільки залишені фази.

---

## Сценарій 3: Пакет задач на ніч

Поставте кілька задач в чергу і залиште монітор працювати.

### Варіант А: Через Claude (рекомендовано)

Скажіть Claude делегувати:

```
Делегуй білдеру:
1. Implement change add-deep-crawling
2. Implement change add-delivery-channels
3. Fix PHPStan errors in dev-reporter-agent
```

Claude створює 3 файли задач в `builder/tasks/todo/` з пріоритетами:
```
builder/tasks/todo/implement-change-add-deep-crawling.md        <!-- priority: 3 -->
builder/tasks/todo/implement-change-add-delivery-channels.md    <!-- priority: 2 -->
builder/tasks/todo/fix-phpstan-dev-reporter.md                  <!-- priority: 1 -->
```

### Варіант Б: Ручні файли задач

```bash
mkdir -p builder/tasks/todo

cat > builder/tasks/todo/implement-deep-crawling.md << 'EOF'
<!-- priority: 3 -->
# Implement change: add-deep-crawling

Реалізувати deep crawling для knowledge-agent.

## OpenSpec

- Proposal: openspec/changes/add-deep-crawling/proposal.md
- Tasks: openspec/changes/add-deep-crawling/tasks.md

## Validation

- PHPStan level 8 passes
- make knowledge-test passes
- make conventions-test passes
EOF
```

### Запустіть монітор

```bash
./builder/monitor/pipeline-monitor.sh
```

Задачі виконуються в порядку пріоритету (найвищий першим). Монітор автоматично бере наступну задачу коли поточна завершується.

### Перевірте результати вранці

```bash
# Огляд
ls builder/tasks/done/
ls builder/tasks/failed/

# Summaries (українською, з вартістю/часом по агенту)
cat builder/tasks/summary/*.md

# Діфи
git log --oneline --all | grep pipeline/
git diff main...pipeline/implement-deep-crawling
```

---

## Сценарій 4: Дві паралельні задачі через tmux

Запустіть дві незалежні задачі одночасно в окремих tmux панелях.

### Підготовка

```bash
# Створити tmux сесію з двома панелями
tmux new-session -d -s pipeline
tmux split-window -h -t pipeline
```

### Запустити задачі паралельно

```bash
# Ліва панель: knowledge-agent фіча
tmux send-keys -t pipeline:0.0 'cd /workspaces/ai-community-platform && opencode' Enter
# Зачекати поки OpenCode стартує, потім:
tmux send-keys -t pipeline:0.0 'ultrawork Implement change add-deep-crawling' Enter

# Права панель: core admin фіча
tmux send-keys -t pipeline:0.1 'cd /workspaces/ai-community-platform && opencode' Enter
tmux send-keys -t pipeline:0.1 'ultrawork Implement change add-tenant-management' Enter
```

### Приєднатися і спостерігати

```bash
tmux attach -t pipeline
```

Навігація: `Ctrl-b ←` / `Ctrl-b →` для переключення між панелями.

### Альтернатива: Builder monitor з MONITOR_WORKERS=2

Якщо надаєте перевагу builder pipeline (bash, не OpenCode):

```bash
# Поставити обидві задачі
cat > builder/tasks/todo/task-a.md << 'EOF'
<!-- priority: 2 -->
# Implement change: add-deep-crawling
...
EOF

cat > builder/tasks/todo/task-b.md << 'EOF'
<!-- priority: 2 -->
# Implement change: add-tenant-management
...
EOF

# Запустити монітор з 2 воркерами
MONITOR_WORKERS=2 ./builder/monitor/pipeline-monitor.sh
```

Обидві задачі працюють паралельно на окремих git worktrees.

---

## Швидка довідка

| Хочу... | Команда |
|---------|---------|
| Повний pipeline (автоматично) | `ultrawork <задача>` або `/auto <задача>` |
| Реалізувати існуючу спеку | `/implement <change-id>` |
| Тільки лінт + тести | `/validate` |
| Тільки аудит | `/audit` |
| Відновити перервану роботу | `/finish` |
| Ручний агент за агентом | `/pipeline <задача>` |
| Поставити задачу в чергу | Скажіть Claude: "делегуй білдеру" |
| Пакет на ніч | Файли в `builder/tasks/todo/`, запустити монітор |
| Паралельно через tmux | Два OpenCode інстанси в tmux панелях |
| Паралельно через монітор | `MONITOR_WORKERS=2 ./builder/monitor/pipeline-monitor.sh` |

## Де знайти результати

| Артифакт | Розташування |
|----------|-------------|
| Хендоф агентів | `.opencode/pipeline/handoff.md` |
| Аудит-репорти | `.opencode/pipeline/reports/*_audit.md` |
| Pipeline план | `pipeline-plan.json` |
| Summaries задач | `builder/tasks/summary/<timestamp>-<slug>.md` |
| Git гілки | `pipeline/<slug>` |
| Batch репорти | `.opencode/pipeline/reports/batch_*.md` |
| Логи агентів | `.opencode/pipeline/logs/*.meta.json` |
