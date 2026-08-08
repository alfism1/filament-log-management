<?php

namespace Alfism1\FilamentLogManagement\Data;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * The filters applied while scanning a log file.
 */
final class LogQuery
{
    /**
     * @param  list<string>  $levels  empty = every level
     * @param  list<string>  $processes  empty = every process
     */
    public function __construct(
        public readonly array $levels = [],
        public readonly array $processes = [],
        public readonly ?string $search = null,
        public readonly ?CarbonImmutable $from = null,
        public readonly ?CarbonImmutable $to = null,
        public readonly int $scanEntries = 2000,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public static function fromArray(array $filters): self
    {
        return new self(
            levels: array_values(array_filter((array) ($filters['levels'] ?? []))),
            processes: array_values(array_filter((array) ($filters['processes'] ?? []))),
            search: filled($filters['search'] ?? null) ? trim((string) $filters['search']) : null,
            from: self::toDate($filters['from'] ?? null)?->startOfDay(),
            to: self::toDate($filters['to'] ?? null)?->endOfDay(),
            scanEntries: max(1, (int) ($filters['depth'] ?? config('log-management.scan_entries', 2000))),
        );
    }

    private static function toDate(mixed $value): ?CarbonImmutable
    {
        if (blank($value)) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Filters that narrow *which* entries exist at all. Facet counts are built
     * from entries passing these, so the level/process counts shown next to the
     * filters stay meaningful while those filters are applied.
     */
    public function matchesScope(LogEntry $entry): bool
    {
        if ($this->from !== null && ($entry->loggedAt === null || $entry->loggedAt->lt($this->from))) {
            return false;
        }

        if ($this->to !== null && ($entry->loggedAt === null || $entry->loggedAt->gt($this->to))) {
            return false;
        }

        // stripos rather than Str::contains: log lines are raw bytes and may not
        // be valid UTF-8, which the mb_* based helpers choke on.
        if ($this->search !== null && $this->search !== '' && stripos($entry->searchable(), $this->search) === false) {
            return false;
        }

        return true;
    }

    /**
     * The facet filters themselves.
     */
    public function matchesFacets(LogEntry $entry): bool
    {
        if ($this->levels !== [] && ! in_array($entry->level, $this->levels, true)) {
            return false;
        }

        if ($this->processes !== [] && ! in_array($entry->processKey, $this->processes, true)) {
            return false;
        }

        return true;
    }

    public function isFiltered(): bool
    {
        return $this->levels !== []
            || $this->processes !== []
            || filled($this->search)
            || $this->from !== null
            || $this->to !== null;
    }
}
