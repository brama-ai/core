# Core Admin UI for Builder-Agent

## Overview

`/admin/coder` provides a web interface for the existing builder workflow:

- task creation
- queue visibility
- worker visibility
- live logs and stage timeline
- retry / cancel / priority updates

## Runtime model

The current release is a phase-1 compatibility delivery:

- Core DB is the primary UI state store
- `builder/tasks/*` remains the compatible runtime layer
- `.opencode/pipeline/*` remains the source for logs, summaries, and artifacts
- the CLI monitor (`builder/monitor/pipeline-monitor.sh`) remains supported

## Main pages

- `/admin/coder`
- `/admin/coder/create`
- `/admin/coder/{id}`

## Worker commands

```bash
cd apps/brama-core
php bin/console coder:worker:start --id=worker-1
php bin/console coder:worker:status
php bin/console coder:worker:stop worker-1
```

## Current limitations

- A2A skill exposure is not enabled yet
- the pipeline still executes through `builder/pipeline.sh`
- SSE uses DB-backed change streaming rather than Redis pub/sub in v1
