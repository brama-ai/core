<?php

declare(strict_types=1);

namespace App\Service;

use NeuronAI\Observability\InspectorObserver;

/**
 * Factory for creating Inspector observer instances.
 * Handles cases where Inspector is not configured.
 */
final class InspectorFactory
{
    public function __construct(
        private readonly ?string $inspectorKey = null,
        private readonly string $transport = 'async',
        private readonly int $maxItems = 100,
        private readonly bool $autoFlush = true,
    ) {
    }

    public function create(): ?InspectorObserver
    {
        // If no Inspector key is provided, return null (monitoring disabled)
        if (empty($this->inspectorKey)) {
            return null;
        }

        try {
            return InspectorObserver::instance(
                key: $this->inspectorKey,
                transport: $this->transport,
                maxItems: $this->maxItems,
                autoFlush: $this->autoFlush
            );
        } catch (\Throwable $e) {
            // If Inspector fails to initialize, log the error but don't break the application
            error_log("Failed to initialize Inspector: " . $e->getMessage());
            return null;
        }
    }
}