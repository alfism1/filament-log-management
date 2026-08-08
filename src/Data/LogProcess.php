<?php

namespace Alfism1\FilamentLogManagement\Data;

/**
 * A "process" is the bucket a log entry belongs to — cron, booking, livechat,
 * queue, ... Configured ones come from config/log-management.php; entries carrying
 * an unrecognised "[tag]" get one synthesised on the fly.
 */
final class LogProcess
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $color = 'gray',
        public readonly string $icon = 'heroicon-o-cube',
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromConfig(string $key, array $config): self
    {
        return new self(
            key: $key,
            label: $config['label'] ?? ucfirst($key),
            color: $config['color'] ?? 'gray',
            icon: $config['icon'] ?? 'heroicon-o-cube',
        );
    }
}
