<?php

namespace Alfism1\FilamentLogManagement\Data;

use Carbon\CarbonImmutable;
use Illuminate\Support\Number;

final class LogFile
{
    public function __construct(
        public readonly string $name,
        public readonly string $path,
        public readonly int $size,
        public readonly int $modifiedAt,
    ) {}

    public static function fromPath(string $path): self
    {
        return new self(
            name: basename($path),
            path: $path,
            size: @filesize($path) ?: 0,
            modifiedAt: @filemtime($path) ?: 0,
        );
    }

    public function modified(): CarbonImmutable
    {
        return CarbonImmutable::createFromTimestamp($this->modifiedAt);
    }

    public function humanSize(): string
    {
        return Number::fileSize($this->size, precision: 1);
    }

    /**
     * "livechat-2026-08-08.log (41 KB)" — used in the file picker.
     */
    public function label(): string
    {
        return sprintf('%s (%s)', $this->name, $this->humanSize());
    }
}
