<?php

declare(strict_types=1);

namespace App\A2AGateway;

final class ConventionResult
{
    /**
     * @param list<string> $violations
     */
    public function __construct(
        /** healthy | degraded | error */
        public readonly string $status,
        public readonly array $violations,
    ) {
    }
}
