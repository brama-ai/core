<?php

declare(strict_types=1);

namespace App\Scheduler;

use Cron\CronExpression;

class CronExpressionHelper
{
    /**
     * Compute the next run time after now for a given cron expression and timezone.
     */
    public function computeNextRun(string $cronExpression, string $timezone = 'UTC'): \DateTimeImmutable
    {
        $tz = new \DateTimeZone($timezone);
        $now = new \DateTime('now', $tz);

        $cron = new CronExpression($cronExpression);
        $next = $cron->getNextRunDate($now, 0, false, $timezone);

        return \DateTimeImmutable::createFromMutable($next);
    }

    /**
     * Check whether a cron expression string is valid.
     */
    public function isValid(string $cronExpression): bool
    {
        return CronExpression::isValidExpression($cronExpression);
    }
}
