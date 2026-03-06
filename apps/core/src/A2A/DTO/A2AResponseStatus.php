<?php

declare(strict_types=1);

namespace App\A2A\DTO;

/**
 * Status of an A2A task response.
 */
enum A2AResponseStatus: string
{
    /** Task completed successfully */
    case Completed = 'completed';

    /** Task failed with an error */
    case Failed = 'failed';

    /** Task has been queued for async processing */
    case Queued = 'queued';
}
