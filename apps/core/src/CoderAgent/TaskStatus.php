<?php

declare(strict_types=1);

namespace App\CoderAgent;

enum TaskStatus: string
{
    case Draft = 'draft';
    case Queued = 'queued';
    case InProgress = 'in_progress';
    case Done = 'done';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
