<?php

declare(strict_types=1);

namespace App\CoderAgent;

enum WorkerStatus: string
{
    case Idle = 'idle';
    case Busy = 'busy';
    case Stopped = 'stopped';
    case Dead = 'dead';
    case Stopping = 'stopping';
}
