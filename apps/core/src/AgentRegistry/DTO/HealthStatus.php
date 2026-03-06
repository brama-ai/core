<?php

declare(strict_types=1);

namespace App\AgentRegistry\DTO;

/**
 * Health status of a registered agent.
 */
enum HealthStatus: string
{
    /** Health status has not been determined yet */
    case Unknown = 'unknown';

    /** Agent is healthy and responding normally */
    case Healthy = 'healthy';

    /** Agent is responding but with degraded performance */
    case Degraded = 'degraded';

    /** Agent health check returned an error */
    case Error = 'error';

    /** Agent is administratively disabled */
    case Disabled = 'disabled';
}
