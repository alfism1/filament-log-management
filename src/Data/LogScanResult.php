<?php

namespace Alfism1\FilamentLogManagement\Data;

use Alfism1\FilamentLogManagement\Support\LogLevel;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * The outcome of scanning one log file: the matching entries (newest first)
 * plus the facet counts and the reason the scan stopped.
 */
final class LogScanResult
{
    /**
     * @param  list<LogEntry>  $entries  matched entries, newest first
     * @param  array<string, int>  $levelCounts
     * @param  array<string, int>  $processCounts
     */
    public function __construct(
        public readonly array $entries = [],
        public readonly array $levelCounts = [],
        public readonly array $processCounts = [],
        public readonly int $scannedEntries = 0,
        public readonly int $bytesRead = 0,
        public readonly bool $reachedStartOfFile = true,
        public readonly bool $truncated = false,
    ) {}

    public static function empty(): self
    {
        return new self();
    }

    public function total(): int
    {
        return count($this->entries);
    }

    public function problemCount(): int
    {
        return array_sum(array_intersect_key($this->levelCounts, array_flip(LogLevel::PROBLEM_LEVELS)));
    }

    public function paginate(int $perPage, int $page): LengthAwarePaginator
    {
        $page = max(1, $page);

        return new LengthAwarePaginator(
            items: array_slice($this->entries, ($page - 1) * $perPage, $perPage),
            total: $this->total(),
            perPage: $perPage,
            currentPage: $page,
            options: ['pageName' => 'page'],
        );
    }
}
