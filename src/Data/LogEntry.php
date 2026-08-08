<?php

namespace Alfism1\FilamentLogManagement\Data;

use Alfism1\FilamentLogManagement\Support\LogLevel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * One parsed line (plus its continuation lines, e.g. a stack trace) from a
 * Laravel log file.
 */
final class LogEntry
{
    public function __construct(
        public readonly string $id,
        public readonly ?CarbonImmutable $loggedAt,
        public readonly string $environment,
        public readonly string $level,
        public readonly string $message,
        public readonly ?string $tag,
        public readonly string $processKey,
        public readonly ?string $context,
        public readonly ?string $stackTrace,
        public readonly string $fileName,
    ) {}

    public function color(): string
    {
        return LogLevel::color($this->level);
    }

    public function icon(): string
    {
        return LogLevel::icon($this->level);
    }

    public function severity(): int
    {
        return LogLevel::SEVERITY[$this->level] ?? 0;
    }

    public function isProblem(): bool
    {
        return in_array($this->level, LogLevel::PROBLEM_LEVELS, true);
    }

    public function time(): string
    {
        return $this->loggedAt?->format('Y-m-d H:i:s') ?? '-';
    }

    public function shortMessage(int $limit = 180): string
    {
        return Str::limit(preg_replace('/\s+/', ' ', $this->message) ?? $this->message, $limit);
    }

    public function hasDetails(): bool
    {
        return filled($this->context)
            || filled($this->stackTrace)
            || mb_strlen($this->message) > 180;
    }

    /**
     * The context re-encoded with newlines/indentation, or the raw string when
     * it is not valid JSON (some libraries append non-JSON extras).
     */
    public function prettyContext(): ?string
    {
        if (blank($this->context)) {
            return null;
        }

        $decoded = json_decode($this->context, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->context;
        }

        return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Everything the free-text search runs against.
     */
    public function searchable(): string
    {
        return $this->message . ' ' . $this->context . ' ' . $this->stackTrace;
    }
}
