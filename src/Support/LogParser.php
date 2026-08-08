<?php

namespace Alfism1\FilamentLogManagement\Support;

use Alfism1\FilamentLogManagement\Data\LogEntry;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Turns raw log text into LogEntry objects.
 *
 * Monolog's default LineFormatter writes:
 *
 *   [%datetime%] %channel%.%LEVEL%: %message% %context% %extra%
 *
 * followed by any number of continuation lines (stack traces). The message and
 * the trailing JSON are separated by walking back from the end of the line and
 * bracket-matching, so a message containing "{" or "[" is not mistaken for the
 * start of the context.
 */
class LogParser
{
    public const HEADER_PATTERN = '/^\[(?P<datetime>\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:\s?[+-]\d{2}:?\d{2}|Z)?)\]\s*(?P<env>[^\s:]*?)\.(?P<level>DEBUG|INFO|NOTICE|WARNING|ERROR|CRITICAL|ALERT|EMERGENCY)\s*:\s?(?P<body>.*)$/s';

    public function __construct(protected LogProcessResolver $processes) {}

    public function isHeader(string $line): bool
    {
        return preg_match(self::HEADER_PATTERN, $line) === 1;
    }

    /**
     * @param  list<string>  $continuationLines  stack trace lines, in file order
     */
    public function parse(string $headerLine, array $continuationLines, string $fileName, string $id): ?LogEntry
    {
        if (! preg_match(self::HEADER_PATTERN, $headerLine, $matches)) {
            return null;
        }

        [$message, $context] = $this->splitBody($matches['body']);

        $stackTrace = trim(implode("\n", $continuationLines));

        // Laravel formats logs with allowInlineLineBreaks, so an exception
        // context is written as JSON whose "exception" string carries real line
        // breaks — the value spills over the header line into what otherwise
        // looks like a stack trace. Re-join and split again to recover it.
        if ($context === '' && $continuationLines !== []) {
            [$message, $context, $stackTrace] = $this->splitMultiLineBody($matches['body'], $continuationLines)
                ?? [$message, $context, $stackTrace];
        }

        $tag = LogProcessResolver::extractTag($message);

        return new LogEntry(
            id: $id,
            loggedAt: $this->parseDate($matches['datetime']),
            environment: $matches['env'] !== '' ? $matches['env'] : 'app',
            level: LogLevel::normalize($matches['level']),
            message: $message,
            tag: $tag,
            processKey: $this->processes->resolve($message, $context, $fileName, $tag),
            context: $context !== '' ? $context : null,
            stackTrace: $stackTrace !== '' ? $stackTrace : null,
            fileName: $fileName,
        );
    }

    protected function parseDate(string $value): ?CarbonImmutable
    {
        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Split "message {...} []" into [message, context].
     *
     * @return array{0: string, 1: string}
     */
    protected function splitBody(string $body): array
    {
        $rest = rtrim($body);
        $blobs = [];

        // At most two trailing JSON blobs: Monolog's %context% and %extra%.
        for ($i = 0; $i < 2; $i++) {
            $start = $this->trailingJsonStart($rest);

            if ($start === null) {
                break;
            }

            $candidate = substr($rest, $start);

            json_decode($candidate, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                break;
            }

            $blobs[] = $candidate;
            $rest = rtrim(substr($rest, 0, $start));
        }

        // Collected newest-first; put them back in written order and drop the
        // empty "[]"/"{}" Monolog emits when there is no context or extra.
        $blobs = array_values(array_filter(
            array_reverse($blobs),
            fn (string $blob) => ! in_array($blob, ['[]', '{}'], true),
        ));

        return [trim($rest), implode(' ', $blobs)];
    }

    /**
     * Recover a context whose JSON value spans the header line and the lines
     * below it.
     *
     * The exception trace is lifted out of the decoded context and returned as
     * the stack trace, so the UI can show it as text instead of one very long
     * escaped JSON string.
     *
     * @param  list<string>  $continuationLines
     * @return array{0: string, 1: string, 2: string}|null  [message, context, stackTrace]
     */
    protected function splitMultiLineBody(string $body, array $continuationLines): ?array
    {
        $joined = rtrim($body . "\n" . implode("\n", $continuationLines));

        $start = $this->trailingJsonStart($joined);

        if ($start === null) {
            return null;
        }

        $decoded = json_decode($this->escapeControlCharacters(substr($joined, $start)), true);

        if (! is_array($decoded)) {
            return null;
        }

        $message = trim(substr($joined, 0, $start));
        $stackTrace = '';

        // "exception" holds "[object] (Type(code: 0): msg at file:line)" plus
        // the [stacktrace] block — the part a human actually reads.
        if (is_string($decoded['exception'] ?? null)) {
            $stackTrace = trim($decoded['exception']);
            unset($decoded['exception']);
        }

        $context = $decoded === []
            ? ''
            : (string) json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

        return [$message, $context, $stackTrace];
    }

    /**
     * Undo the real line breaks Monolog writes inside JSON string values, so
     * the value becomes decodable JSON again.
     */
    protected function escapeControlCharacters(string $value): string
    {
        return str_replace(["\r\n", "\n", "\r", "\t"], ['\n', '\n', '\n', '\t'], $value);
    }

    /**
     * Index of the "{" or "[" opening the JSON value that ends the string, or
     * null when the string does not end with a balanced JSON value.
     */
    protected function trailingJsonStart(string $value): ?int
    {
        $length = strlen($value);

        if ($length === 0) {
            return null;
        }

        $last = $value[$length - 1];

        if ($last !== '}' && $last !== ']') {
            return null;
        }

        $depth = 0;
        $inString = false;

        for ($i = $length - 1; $i >= 0; $i--) {
            $character = $value[$i];

            if ($inString) {
                if ($character === '"' && ! $this->isEscaped($value, $i)) {
                    $inString = false;
                }

                continue;
            }

            if ($character === '"') {
                $inString = true;

                continue;
            }

            if ($character === '}' || $character === ']') {
                $depth++;

                continue;
            }

            if ($character === '{' || $character === '[') {
                $depth--;

                if ($depth === 0) {
                    return $i;
                }

                if ($depth < 0) {
                    return null;
                }
            }
        }

        return null;
    }

    protected function isEscaped(string $value, int $position): bool
    {
        $backslashes = 0;

        for ($i = $position - 1; $i >= 0 && $value[$i] === '\\'; $i--) {
            $backslashes++;
        }

        return $backslashes % 2 === 1;
    }
}
