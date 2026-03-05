<?php

declare(strict_types=1);

namespace App\Logging;

final class LogSettingsProvider
{
    private const DEFAULTS = [
        'log_level' => 'DEBUG',
        'retention_days' => 7,
        'max_size_gb' => 2,
    ];

    public function __construct(
        private readonly string $settingsPath,
    ) {
    }

    /**
     * @return array{log_level: string, retention_days: int, max_size_gb: int}
     */
    public function load(): array
    {
        if (!is_file($this->settingsPath)) {
            return self::DEFAULTS;
        }

        $content = file_get_contents($this->settingsPath);

        if (false === $content) {
            return self::DEFAULTS;
        }

        /** @var array<string, mixed>|null $data */
        $data = json_decode($content, true);

        if (!\is_array($data)) {
            return self::DEFAULTS;
        }

        return [
            'log_level' => \is_string($data['log_level'] ?? null) ? strtoupper((string) $data['log_level']) : self::DEFAULTS['log_level'],
            'retention_days' => \is_int($data['retention_days'] ?? null) ? (int) $data['retention_days'] : self::DEFAULTS['retention_days'],
            'max_size_gb' => \is_int($data['max_size_gb'] ?? null) || \is_float($data['max_size_gb'] ?? null) ? (int) $data['max_size_gb'] : self::DEFAULTS['max_size_gb'],
        ];
    }

    /**
     * @param array{log_level: string, retention_days: int, max_size_gb: int} $settings
     */
    public function save(array $settings): void
    {
        $dir = \dirname($this->settingsPath);

        if (!is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        file_put_contents(
            $this->settingsPath,
            json_encode($settings, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
        );
    }

    public function getSettingsPath(): string
    {
        return $this->settingsPath;
    }
}
