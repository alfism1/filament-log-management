<?php

namespace Alfism1\FilamentLogManagement\Support;

use Alfism1\FilamentLogManagement\Data\LogFile;
use Alfism1\FilamentLogManagement\Data\LogQuery;
use Alfism1\FilamentLogManagement\Data\LogScanResult;

/**
 * Reads a log file newest-entry-first.
 *
 * Log files grow without bound, so the whole file is never loaded: it is read
 * in chunks from the end and parsed in reverse, stopping at the first of
 *
 *   - the requested scan depth (entries),
 *   - the configured byte cap,
 *   - the start of the file.
 *
 * Filters are applied during the scan, so only matching entries are kept in
 * memory. What the page shows is therefore "the matches within the last N
 * entries", which the UI states explicitly whenever the scan stopped early.
 */
class LogReader
{
    /** Bytes pulled per read. Large enough that a stack trace rarely spans two. */
    protected const CHUNK_SIZE = 262144;

    public function __construct(protected LogParser $parser) {}

    public function scan(LogFile $file, LogQuery $query): LogScanResult
    {
        $handle = @fopen($file->path, 'rb');

        if ($handle === false) {
            return LogScanResult::empty();
        }

        $maxBytes = (int) config('log-management.max_bytes', 64 * 1024 * 1024);
        $maxMatches = (int) config('log-management.max_matches', 5000);

        $entries = [];
        $levelCounts = [];
        $processCounts = [];
        $seenIds = [];

        $scanned = 0;
        $bytesRead = 0;
        $truncated = false;

        $position = $file->size;
        $remainder = '';
        // Continuation lines are met before their header when reading in
        // reverse, so they queue up here until the header shows up.
        $pending = [];

        try {
            while ($position > 0) {
                if ($scanned >= $query->scanEntries || $bytesRead >= $maxBytes) {
                    $truncated = true;

                    break;
                }

                $chunkSize = min(self::CHUNK_SIZE, $position);
                $position -= $chunkSize;

                fseek($handle, $position);
                $chunk = fread($handle, $chunkSize);

                if ($chunk === false) {
                    break;
                }

                $bytesRead += $chunkSize;

                $lines = explode("\n", $chunk . $remainder);

                // Unless we just read the head of the file, the first element is
                // the tail of a line whose start lies in the next chunk back.
                $remainder = $position > 0 ? array_shift($lines) : '';

                for ($i = count($lines) - 1; $i >= 0; $i--) {
                    $line = rtrim($lines[$i], "\r");

                    if (! $this->parser->isHeader($line)) {
                        $pending[] = $line;

                        continue;
                    }

                    $continuation = array_reverse($pending);
                    $pending = [];

                    $entry = $this->parser->parse(
                        headerLine: $line,
                        continuationLines: $continuation,
                        fileName: $file->name,
                        id: $this->makeId($line, $continuation, $seenIds),
                    );

                    $scanned++;

                    if ($entry !== null && $query->matchesScope($entry)) {
                        $levelCounts[$entry->level] = ($levelCounts[$entry->level] ?? 0) + 1;
                        $processCounts[$entry->processKey] = ($processCounts[$entry->processKey] ?? 0) + 1;

                        if ($query->matchesFacets($entry)) {
                            if (count($entries) >= $maxMatches) {
                                $truncated = true;

                                break 2;
                            }

                            $entries[] = $entry;
                        }
                    }

                    if ($scanned >= $query->scanEntries) {
                        $truncated = true;

                        break 2;
                    }
                }
            }
        } finally {
            fclose($handle);
        }

        arsort($levelCounts);
        arsort($processCounts);

        return new LogScanResult(
            entries: $entries,
            levelCounts: $levelCounts,
            processCounts: $processCounts,
            scannedEntries: $scanned,
            bytesRead: $bytesRead,
            reachedStartOfFile: $position <= 0 && ! $truncated,
            truncated: $truncated,
        );
    }

    /**
     * Content-derived id so an expanded row stays expanded across refreshes.
     * Identical repeated lines get a suffix to keep ids unique.
     *
     * @param  list<string>  $continuation
     * @param  array<string, int>  $seen
     */
    protected function makeId(string $header, array $continuation, array &$seen): string
    {
        $hash = substr(md5($header . implode("\n", $continuation)), 0, 16);

        $occurrence = $seen[$hash] = ($seen[$hash] ?? 0) + 1;

        return $occurrence === 1 ? $hash : $hash . '-' . $occurrence;
    }
}
