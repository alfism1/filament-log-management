<?php

namespace Alfism1\FilamentLogManagement\Support;

use Alfism1\FilamentLogManagement\Data\LogProcess;
use Illuminate\Support\Str;

/**
 * Decides which process produced a log entry.
 *
 * Resolution order (first hit wins):
 *   1. an explicit "[tag]" prefix on the message, matched against each
 *      process key and its aliases — an unknown tag becomes its own process;
 *   2. the log file name, against a process' "files" globs;
 *   3. the configured regex patterns, against "<message> <context>";
 *   4. the configured fallback process.
 */
class LogProcessResolver
{
    /** @var array<string, array<string, mixed>> */
    protected array $processes;

    /** @var array<string, mixed> */
    protected array $fallback;

    /** @var array<string, string> alias => process key */
    protected array $aliases = [];

    /** @var array<string, LogProcess> */
    protected array $resolved = [];

    public function __construct(?array $processes = null, ?array $fallback = null)
    {
        $this->processes = $processes ?? config('log-management.processes', []);
        $this->fallback = $fallback ?? config('log-management.fallback', [
            'key' => 'app',
            'label' => 'Application',
            'color' => 'gray',
            'icon' => 'heroicon-o-cube',
        ]);

        foreach ($this->processes as $key => $process) {
            $this->aliases[strtolower($key)] = $key;

            foreach ($process['aliases'] ?? [] as $alias) {
                $this->aliases[strtolower($alias)] = $key;
            }
        }
    }

    public function fallbackKey(): string
    {
        return $this->fallback['key'] ?? 'app';
    }

    /**
     * Pull a leading bracket tag off a message: "[livechat] session_start".
     */
    public static function extractTag(string $message): ?string
    {
        if (preg_match('/^\[([A-Za-z0-9_.\- ]{1,40})\]\s*/', $message, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    public function resolve(string $message, ?string $context = null, ?string $fileName = null, ?string $tag = null): string
    {
        if (filled($tag)) {
            $key = $this->aliases[strtolower($tag)] ?? null;

            if ($key !== null) {
                return $key;
            }

            // An unrecognised tag is still a deliberate grouping by whoever
            // wrote the log call, so keep it as its own process.
            return Str::slug($tag) ?: $this->fallbackKey();
        }

        if (filled($fileName)) {
            foreach ($this->processes as $key => $process) {
                foreach ($process['files'] ?? [] as $glob) {
                    if (fnmatch($glob, $fileName)) {
                        return $key;
                    }
                }
            }
        }

        $haystack = trim($message . ' ' . $context);

        foreach ($this->processes as $key => $process) {
            foreach ($process['patterns'] ?? [] as $pattern) {
                if (@preg_match($pattern, $haystack) === 1) {
                    return $key;
                }
            }
        }

        return $this->fallbackKey();
    }

    /**
     * Label/colour/icon for a process key, including keys synthesised from an
     * unknown tag.
     */
    public function describe(string $key): LogProcess
    {
        if (isset($this->resolved[$key])) {
            return $this->resolved[$key];
        }

        if (isset($this->processes[$key])) {
            return $this->resolved[$key] = LogProcess::fromConfig($key, $this->processes[$key]);
        }

        if ($key === $this->fallbackKey()) {
            return $this->resolved[$key] = LogProcess::fromConfig($key, $this->fallback);
        }

        return $this->resolved[$key] = new LogProcess(
            key: $key,
            label: Str::headline($key),
            color: 'gray',
            icon: 'heroicon-o-tag',
        );
    }

    /**
     * Every configured process, in config order, with the fallback last.
     *
     * @return array<string, LogProcess>
     */
    public function configured(): array
    {
        $processes = [];

        foreach (array_keys($this->processes) as $key) {
            $processes[$key] = $this->describe($key);
        }

        $fallbackKey = $this->fallbackKey();
        $processes[$fallbackKey] = $this->describe($fallbackKey);

        return $processes;
    }
}
