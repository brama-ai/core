---
description: "Run sequential pipeline with manual agent switching"
agent: planner
---

Run the sequential pipeline for this task.

You are the Planner. Analyze the task and write `pipeline-plan.json`.
After you finish, tell the user which agent to switch to next (Tab → @agent).
Each agent reads handoff.md and appends their section.

Pipeline order: planner → architect (if needed) → coder → reviewer → validator → tester → auditor (if agent task) → documenter (if needed) → summarizer.

Start by reading AGENTS.md and analyzing the task.
