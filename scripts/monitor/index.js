#!/usr/bin/env node
import React, { useState, useEffect, useCallback } from "react";
import { render, Box, Text, useApp, useInput, useStdout } from "ink";
import { execSync } from "child_process";
import { readdirSync, readFileSync, existsSync, writeFileSync, renameSync, mkdirSync } from "fs";
import { join, basename, resolve, dirname } from "path";
import { fileURLToPath } from "url";

const __dirname = dirname(fileURLToPath(import.meta.url));
const REPO_ROOT = resolve(__dirname, "../..");

// ---------------------------------------------------------------------------
// Data helpers
// ---------------------------------------------------------------------------

function findTaskSource() {
  const arg = process.argv[2];
  return arg ? resolve(arg) : join(REPO_ROOT, "tasks");
}

function findWorktreeBase() {
  const p1 = join(REPO_ROOT, ".pipeline-worktrees");
  if (existsSync(p1)) return p1;
  return join(REPO_ROOT, ".opencode/pipeline/worktrees");
}

const TASK_SOURCE = findTaskSource();
const WORKTREE_BASE = findWorktreeBase();
const LOG_DIR = join(REPO_ROOT, ".opencode/pipeline/logs");

function listMd(dir) {
  if (!existsSync(dir)) return [];
  return readdirSync(dir)
    .filter((f) => f.endsWith(".md") && f !== ".gitkeep")
    .map((f) => join(dir, f));
}

function getTitle(file) {
  try {
    const lines = readFileSync(file, "utf8").split("\n");
    for (const l of lines) {
      if (l.startsWith("# ")) return l.slice(2).trim();
    }
  } catch {}
  return basename(file, ".md");
}

function getPriority(file) {
  try {
    const first = readFileSync(file, "utf8").split("\n")[0];
    const m = first.match(/<!--\s*priority:\s*(\d+)\s*-->/);
    if (m) return parseInt(m[1], 10);
  } catch {}
  return 1;
}

function setPriority(file, prio) {
  try {
    let content = readFileSync(file, "utf8");
    const lines = content.split("\n");
    if (lines[0].match(/<!--\s*priority:\s*\d+\s*-->/)) {
      lines[0] = `<!-- priority: ${prio} -->`;
    } else {
      lines.unshift(`<!-- priority: ${prio} -->`);
    }
    writeFileSync(file, lines.join("\n"));
  } catch {}
}

function getBatchMeta(file) {
  try {
    const content = readFileSync(file, "utf8");
    const m = content.match(/<!-- batch:.*?duration: (\d+)s.*?branch: (\S+)/);
    if (m) return { duration: parseInt(m[1], 10), branch: m[2] };
  } catch {}
  return null;
}

function formatDuration(secs) {
  if (secs >= 3600) return `${Math.floor(secs / 3600)}h ${Math.floor((secs % 3600) / 60)}m`;
  if (secs >= 60) return `${Math.floor(secs / 60)}m ${secs % 60}s`;
  return `${secs}s`;
}

// ---------------------------------------------------------------------------
// Task list builder
// ---------------------------------------------------------------------------

function buildTaskList() {
  const tasks = [];

  // In-progress
  for (const f of listMd(join(TASK_SOURCE, "in-progress"))) {
    tasks.push({ file: f, title: getTitle(f), state: "in-progress" });
  }

  // Done
  for (const f of listMd(join(TASK_SOURCE, "done"))) {
    tasks.push({ file: f, title: getTitle(f), state: "done", meta: getBatchMeta(f) });
  }

  // Failed
  for (const f of listMd(join(TASK_SOURCE, "failed"))) {
    tasks.push({ file: f, title: getTitle(f), state: "failed", meta: getBatchMeta(f) });
  }

  // Todo — sorted by priority desc
  const todoFiles = listMd(join(TASK_SOURCE, "todo"));
  const todos = todoFiles.map((f) => ({
    file: f,
    title: getTitle(f),
    state: "todo",
    priority: getPriority(f),
  }));
  todos.sort((a, b) => b.priority - a.priority);
  tasks.push(...todos);

  return tasks;
}

// ---------------------------------------------------------------------------
// Process helpers
// ---------------------------------------------------------------------------

function getBatchPid() {
  try {
    const out = execSync("pgrep -f pipeline-batch.sh 2>/dev/null", { encoding: "utf8" }).trim();
    const pid = out.split("\n")[0] || null;
    if (!pid) return null;
    // Check if the process is actually doing something (has children or recent CPU)
    try {
      const stat = execSync(`ps -o stat= -p ${pid}`, { encoding: "utf8" }).trim();
      // Zombie (Z) or stopped (T) processes are effectively dead
      if (stat.includes("Z") || stat.includes("T")) return null;
    } catch { return null; }
    return pid;
  } catch {
    return null;
  }
}

function getBatchElapsed(pid) {
  try {
    // macOS: use etime (elapsed time) directly — format is [[dd-]hh:]mm:ss
    const etime = execSync(`ps -o etime= -p ${pid}`, { encoding: "utf8" }).trim();
    // Parse etime: "01:23" or "01:02:03" or "1-01:02:03"
    const parts = etime.replace(/-/g, ":").split(":").reverse().map(Number);
    let secs = (parts[0] || 0) + (parts[1] || 0) * 60 + (parts[2] || 0) * 3600 + (parts[3] || 0) * 86400;
    return secs;
  } catch {
    return null;
  }
}

function detectWorkers() {
  if (!existsSync(WORKTREE_BASE)) return [];
  return readdirSync(WORKTREE_BASE)
    .filter((d) => d.startsWith("worker-"))
    .sort();
}

function getWorkerLog(workerName) {
  const logDir = join(WORKTREE_BASE, workerName, ".opencode/pipeline/logs");
  if (!existsSync(logDir)) return null;
  const logs = readdirSync(logDir)
    .filter((f) => f.endsWith(".log"))
    .sort()
    .reverse();
  return logs[0] ? join(logDir, logs[0]) : null;
}

function readLogTail(file, lines = 30) {
  if (!file || !existsSync(file)) return [];
  try {
    const content = readFileSync(file, "utf8");
    return content.split("\n").slice(-lines);
  } catch {
    return [];
  }
}

// ---------------------------------------------------------------------------
// Actions
// ---------------------------------------------------------------------------

const BATCH_WORKERS = parseInt(process.env.PIPELINE_WORKERS || "1", 10);

function actionStart() {
  const pid = getBatchPid();
  if (pid) return "Batch already running";
  const todoCount = listMd(join(TASK_SOURCE, "todo")).length;
  if (todoCount === 0) return "No tasks in todo/";
  try {
    execSync(
      `caffeinate -s nohup ${REPO_ROOT}/scripts/pipeline-batch.sh --workers ${BATCH_WORKERS} --no-stop-on-failure --auto-fix --watch ${TASK_SOURCE} > ${REPO_ROOT}/batch.log 2>&1 &`,
      { cwd: REPO_ROOT, shell: true }
    );
    return `Started batch (${todoCount} tasks, ${BATCH_WORKERS} workers)`;
  } catch (e) {
    return `Start failed: ${e.message}`;
  }
}

function actionKill() {
  try {
    execSync("pkill -f pipeline-batch.sh 2>/dev/null; pkill -f 'opencode run --agent' 2>/dev/null", { shell: true });
    return "Killed batch";
  } catch {
    return "No batch running";
  }
}

function actionRetryFailed() {
  if (getBatchPid()) return "Kill batch first (k)";
  const failDir = join(TASK_SOURCE, "failed");
  const todoDir = join(TASK_SOURCE, "todo");
  if (!existsSync(failDir)) return "No failed/ directory";
  mkdirSync(todoDir, { recursive: true });
  const files = listMd(failDir);
  let count = 0;
  for (const f of files) {
    try {
      let content = readFileSync(f, "utf8");
      // Remove batch metadata
      content = content.replace(/^<!-- batch:.*-->\n?/gm, "");
      // Delete old branch
      const bm = content.match(/branch: (\S+)/);
      if (bm) {
        try { execSync(`git -C "${REPO_ROOT}" branch -D "${bm[1]}" 2>/dev/null`); } catch {}
      }
      writeFileSync(join(todoDir, basename(f)), content);
      execSync(`rm -f "${f}"`);
      count++;
    } catch {}
  }
  if (count === 0) return "No failed tasks";
  // Auto-start after retry
  const startMsg = actionStart();
  return `Moved ${count} failed->todo. ${startMsg}`;
}

// ---------------------------------------------------------------------------
// Components
// ---------------------------------------------------------------------------

function ProgressBar({ done, total, width = 40 }) {
  if (total === 0) return React.createElement(Text, { dimColor: true }, `[${"░".repeat(width)}] 0/0`);
  const filled = Math.round((done / total) * width);
  const empty = width - filled;
  return React.createElement(
    Text,
    null,
    React.createElement(Text, { color: "green" }, "["),
    React.createElement(Text, { color: "green" }, "█".repeat(filled)),
    React.createElement(Text, { dimColor: true }, "░".repeat(empty)),
    React.createElement(Text, { color: "green" }, "]"),
    React.createElement(Text, null, ` ${done}/${total}`)
  );
}

function StatusCards({ todo, inProgress, done, failed }) {
  return React.createElement(
    Box,
    { gap: 2 },
    React.createElement(Text, { color: "blue", bold: true }, `Todo: ${todo}`),
    React.createElement(Text, { color: "yellow", bold: true }, `In Progress: ${inProgress}`),
    React.createElement(Text, { color: "green", bold: true }, `Done: ${done}`),
    React.createElement(Text, { color: "red", bold: true }, `Failed: ${failed}`)
  );
}

function TaskLine({ task, selected }) {
  const prefix = selected ? React.createElement(Text, { color: "cyan" }, "▶ ") : React.createElement(Text, null, "  ");

  let icon, color;
  switch (task.state) {
    case "in-progress":
      icon = "▸";
      color = "yellow";
      break;
    case "done":
      icon = "✓";
      color = "green";
      break;
    case "failed":
      icon = "✗";
      color = "red";
      break;
    default:
      icon = "○";
      color = undefined;
      break;
  }

  let suffix = "";
  if (task.meta) {
    suffix = ` (${formatDuration(task.meta.duration)}${task.meta.branch ? ", " + task.meta.branch : ""})`;
  }
  if (task.state === "todo" && task.priority > 1) {
    suffix = ` #${task.priority}`;
  }

  return React.createElement(
    Box,
    null,
    prefix,
    React.createElement(Text, { color }, `${icon} `),
    React.createElement(Text, { bold: selected }, task.title),
    suffix && React.createElement(Text, { dimColor: true }, suffix)
  );
}

function HLine() {
  const { stdout } = useStdout();
  const w = stdout?.columns || 80;
  return React.createElement(Text, { dimColor: true }, "─".repeat(w));
}

function TabBar({ currentTab, workers }) {
  const tabs = [{ id: 1, label: "Overview" }];
  workers.forEach((w, i) => tabs.push({ id: i + 2, label: w }));

  return React.createElement(
    Box,
    { gap: 1 },
    ...tabs.map((t) =>
      React.createElement(
        Text,
        {
          key: t.id,
          bold: currentTab === t.id,
          inverse: currentTab === t.id,
          dimColor: currentTab !== t.id,
        },
        ` ${t.id}:${t.label} `
      )
    )
  );
}

function TaskDetail({ file, onBack }) {
  let content = "";
  try {
    content = readFileSync(file, "utf8")
      .replace(/^<!-- priority:.*-->\n?/gm, "")
      .replace(/^<!-- batch:.*-->\n?/gm, "");
  } catch {}

  const { stdout } = useStdout();
  const maxLines = (stdout?.rows || 24) - 10;
  const lines = content.split("\n").slice(0, maxLines);

  return React.createElement(
    Box,
    { flexDirection: "column", paddingLeft: 1 },
    React.createElement(Text, { bold: true }, `Task Detail  `),
    React.createElement(Text, { dimColor: true }, "(Esc to go back)"),
    React.createElement(Text, null, ""),
    ...lines.map((l, i) => React.createElement(Text, { key: i }, l)),
    React.createElement(Text, null, ""),
    React.createElement(HLine, null),
    React.createElement(Text, { dimColor: true }, "  Esc back  q quit")
  );
}

function WorkerTab({ workerName }) {
  const logFile = getWorkerLog(workerName);
  const logLines = readLogTail(logFile, 30);
  const agentName = logFile ? basename(logFile, ".log").replace(/^[0-9_]*/, "") : "";

  return React.createElement(
    Box,
    { flexDirection: "column", paddingLeft: 1 },
    React.createElement(
      Box,
      { gap: 2 },
      React.createElement(Text, { bold: true }, "Agent: "),
      React.createElement(Text, { color: "yellow" }, agentName || "none"),
      logFile && React.createElement(Text, { dimColor: true }, basename(logFile))
    ),
    React.createElement(HLine, null),
    ...logLines.map((l, i) => {
      let color;
      if (/error|Error|FAIL|auto-rejecting/.test(l)) color = "red";
      else if (/✓|PASS|success/.test(l)) color = "green";
      return React.createElement(Text, { key: i, color }, `  ${l}`);
    }),
    React.createElement(Text, null, ""),
    React.createElement(HLine, null),
    React.createElement(Text, { dimColor: true }, "  ←/→ tabs  s start  k kill  q quit  (auto-refresh 3s)")
  );
}

function BottomMenu({ selectedState, batchRunning }) {
  let contextKeys = "";
  switch (selectedState) {
    case "in-progress":
      contextKeys = " [x] stop";
      break;
    case "failed":
      contextKeys = " [f] retry";
      break;
    case "todo":
      contextKeys = " [+] priority+  [-] priority-";
      break;
  }

  const startLabel = batchRunning && selectedState === "todo" ? "[s] boost next" : "[s] start";

  return React.createElement(
    Text,
    { dimColor: true },
    `  ←/→ tabs  ↑/↓ select  Enter detail${contextKeys}  ${startLabel}  [k] kill  [q] quit`
  );
}

// ---------------------------------------------------------------------------
// Main App
// ---------------------------------------------------------------------------

function App() {
  const { exit } = useApp();
  const { stdout } = useStdout();
  const [tick, setTick] = useState(0);
  const [tab, setTab] = useState(1);
  const [selectedIdx, setSelectedIdx] = useState(0);
  const [detailFile, setDetailFile] = useState(null);
  const [actionMsg, setActionMsg] = useState("");

  // Auto-refresh every 3s
  useEffect(() => {
    const id = setInterval(() => setTick((t) => t + 1), 3000);
    return () => clearInterval(id);
  }, []);

  // Clear action message after 5s
  useEffect(() => {
    if (actionMsg) {
      const id = setTimeout(() => setActionMsg(""), 5000);
      return () => clearTimeout(id);
    }
  }, [actionMsg]);

  const tasks = buildTaskList();
  const workers = detectWorkers();
  const maxTabs = 1 + workers.length;

  const todoCount = listMd(join(TASK_SOURCE, "todo")).length;
  const inProgressCount = listMd(join(TASK_SOURCE, "in-progress")).length;
  const doneCount = listMd(join(TASK_SOURCE, "done")).length;
  const failedCount = listMd(join(TASK_SOURCE, "failed")).length;
  const total = todoCount + inProgressCount + doneCount + failedCount;
  const completed = doneCount + failedCount;

  const batchPid = getBatchPid();
  const elapsed = batchPid ? getBatchElapsed(batchPid) : null;

  const clampedIdx = Math.min(selectedIdx, Math.max(0, tasks.length - 1));
  if (clampedIdx !== selectedIdx) setSelectedIdx(clampedIdx);

  const selectedTask = tasks[clampedIdx] || null;
  const termRows = stdout?.rows || 24;
  const barWidth = Math.min(50, (stdout?.columns || 80) - 20);

  useInput((input, key) => {
    // Detail mode
    if (detailFile) {
      if (key.escape || input === "q" || input === "Q") {
        setDetailFile(null);
      }
      return;
    }

    if (input === "q" || input === "Q") return exit();
    if (input === "r" || input === "R") return setTick((t) => t + 1);

    // Tab navigation
    if (key.leftArrow) return setTab((t) => Math.max(1, t - 1));
    if (key.rightArrow) return setTab((t) => Math.min(maxTabs, t + 1));
    if (input >= "1" && input <= "9" && parseInt(input) <= maxTabs) return setTab(parseInt(input));

    // Task navigation (overview only)
    if (tab === 1) {
      if (key.upArrow) return setSelectedIdx((i) => Math.max(0, i - 1));
      if (key.downArrow) return setSelectedIdx((i) => Math.min(tasks.length - 1, i + 1));
      if (key.return && selectedTask) return setDetailFile(selectedTask.file);

      // Priority (+/-)  — cursor follows the task after reorder
      if ((input === "+" || input === "=") && selectedTask?.state === "todo") {
        const newP = (selectedTask.priority || 1) + 1;
        setPriority(selectedTask.file, newP);
        setActionMsg(`Priority -> #${newP}`);
        setTick((t) => t + 1);
        // Find new index after re-sort: task moves up in the todo section
        const newTasks = buildTaskList();
        const newIdx = newTasks.findIndex((t) => t.file === selectedTask.file);
        if (newIdx >= 0) setSelectedIdx(newIdx);
        return;
      }
      if (input === "-" && selectedTask?.state === "todo") {
        const newP = Math.max(1, (selectedTask.priority || 1) - 1);
        setPriority(selectedTask.file, newP);
        setActionMsg(`Priority -> #${newP}`);
        setTick((t) => t + 1);
        const newTasks = buildTaskList();
        const newIdx = newTasks.findIndex((t) => t.file === selectedTask.file);
        if (newIdx >= 0) setSelectedIdx(newIdx);
        return;
      }

      // Stop in-progress
      if ((input === "x" || input === "X") && selectedTask?.state === "in-progress") {
        try {
          mkdirSync(join(TASK_SOURCE, "todo"), { recursive: true });
          let content = readFileSync(selectedTask.file, "utf8").replace(/^<!-- batch:.*-->\n?/gm, "");
          writeFileSync(join(TASK_SOURCE, "todo", basename(selectedTask.file)), content);
          execSync(`rm -f "${selectedTask.file}"`);
          setActionMsg(`Stopped: ${selectedTask.title}`);
          setTick((t) => t + 1);
        } catch {}
        return;
      }
    }

    // Global actions
    if (input === "s" || input === "S") {
      // If a todo task is selected and batch is running, boost its priority to be next
      if (tab === 1 && selectedTask?.state === "todo" && getBatchPid()) {
        const allTasks = buildTaskList();
        const maxPrio = Math.max(...allTasks.filter(t => t.state === "todo").map(t => t.priority || 1), 1);
        setPriority(selectedTask.file, maxPrio + 1);
        setActionMsg(`Boosted "${selectedTask.title}" → next in queue (#${maxPrio + 1})`);
        const newTasks = buildTaskList();
        const newIdx = newTasks.findIndex((t) => t.file === selectedTask.file);
        if (newIdx >= 0) setSelectedIdx(newIdx);
        setTick((t) => t + 1);
      } else {
        setActionMsg(actionStart());
        setTick((t) => t + 1);
      }
    }
    if (input === "k" || input === "K") {
      setActionMsg(actionKill());
      setTick((t) => t + 1);
    }
    if (input === "f" || input === "F") {
      setActionMsg(actionRetryFailed());
      setTick((t) => t + 1);
    }
  });

  // Detail view
  if (detailFile) {
    return React.createElement(
      Box,
      { flexDirection: "column" },
      React.createElement(
        Box,
        { gap: 1, paddingLeft: 1 },
        React.createElement(Text, { color: "cyan", bold: true }, "Pipeline Monitor"),
        React.createElement(Text, { dimColor: true }, new Date().toLocaleTimeString())
      ),
      React.createElement(HLine, null),
      React.createElement(TabBar, { currentTab: tab, workers }),
      React.createElement(TaskDetail, { file: detailFile })
    );
  }

  // Worker tab
  if (tab > 1) {
    const workerName = workers[tab - 2];
    return React.createElement(
      Box,
      { flexDirection: "column" },
      React.createElement(
        Box,
        { gap: 1, paddingLeft: 1 },
        React.createElement(Text, { color: "cyan", bold: true }, "Pipeline Monitor"),
        React.createElement(Text, { dimColor: true }, `${workerName}  ${new Date().toLocaleTimeString()}`)
      ),
      React.createElement(HLine, null),
      React.createElement(TabBar, { currentTab: tab, workers }),
      React.createElement(Text, null, ""),
      workerName
        ? React.createElement(WorkerTab, { workerName })
        : React.createElement(Text, { dimColor: true }, "  No worker")
    );
  }

  // Overview tab
  const visibleTasks = tasks.slice(0, termRows - 16);
  let prevState = "";

  const taskElements = [];
  visibleTasks.forEach((task, i) => {
    const stateBase = task.state;
    if (stateBase !== prevState) {
      const labels = {
        "in-progress": ["In Progress:", "yellow"],
        done: ["Completed:", "green"],
        failed: ["Failed:", "red"],
        todo: ["Waiting: (priority order)", "blue"],
      };
      const [label, color] = labels[stateBase] || [stateBase, undefined];
      taskElements.push(
        React.createElement(Text, { key: `hdr-${stateBase}`, color, bold: true }, `  ${label}`)
      );
      prevState = stateBase;
    }
    taskElements.push(
      React.createElement(TaskLine, { key: task.file, task, selected: i === clampedIdx })
    );
  });
  if (tasks.length > visibleTasks.length) {
    taskElements.push(
      React.createElement(Text, { key: "more", dimColor: true }, `    ... ${tasks.length - visibleTasks.length} more`)
    );
  }

  return React.createElement(
    Box,
    { flexDirection: "column" },
    // Header
    React.createElement(
      Box,
      { gap: 1, paddingLeft: 1 },
      React.createElement(Text, { color: "cyan", bold: true }, "Pipeline Monitor"),
      React.createElement(Text, { dimColor: true }, new Date().toLocaleTimeString())
    ),
    React.createElement(HLine, null),
    React.createElement(TabBar, { currentTab: tab, workers }),
    React.createElement(Text, null, ""),
    // Progress
    React.createElement(
      Box,
      { paddingLeft: 1 },
      React.createElement(ProgressBar, { done: completed, total, width: barWidth })
    ),
    React.createElement(Text, null, ""),
    // Status cards
    React.createElement(Box, { paddingLeft: 1 }, React.createElement(StatusCards, { todo: todoCount, inProgress: inProgressCount, done: doneCount, failed: failedCount })),
    React.createElement(Text, null, ""),
    // Batch status
    React.createElement(
      Box,
      { paddingLeft: 1 },
      React.createElement(Text, { bold: true }, "Status: "),
      batchPid
        ? React.createElement(
            Text,
            { color: "green" },
            `Running  (${elapsed !== null ? formatDuration(elapsed) + " elapsed, " : ""}PID ${batchPid})`
          )
        : React.createElement(Text, { dimColor: true }, "Not running")
    ),
    React.createElement(Text, null, ""),
    React.createElement(HLine, null),
    // Task list
    ...taskElements,
    React.createElement(Text, null, ""),
    React.createElement(HLine, null),
    // Action message
    actionMsg ? React.createElement(Text, { color: "yellow", paddingLeft: 1 }, `  ${actionMsg}`) : null,
    // Bottom menu
    React.createElement(
      Box,
      { paddingLeft: 1 },
      React.createElement(BottomMenu, { selectedState: selectedTask?.state, batchRunning: !!batchPid })
    )
  );
}

// ---------------------------------------------------------------------------
// Entry point
// ---------------------------------------------------------------------------
const { waitUntilExit } = render(React.createElement(App), { exitOnCtrlC: true });
await waitUntilExit();
