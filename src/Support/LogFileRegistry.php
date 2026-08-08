<?php

namespace Alfism1\FilamentLogManagement\Support;

use Alfism1\FilamentLogManagement\Data\LogFile;
use Illuminate\Support\Collection;

/**
 * Discovers the readable log files under the configured directory.
 *
 * Every lookup goes through find(), which resolves the requested name against
 * the discovered set — a file name arriving from the browser can therefore
 * never escape the log directory via "../" or a symlink.
 */
class LogFileRegistry
{
    public function __construct(
        protected ?string $directory = null,
        protected ?string $pattern = null,
    ) {
        $this->directory = $directory ?? config('log-management.path', storage_path('logs'));
        $this->pattern = $pattern ?? config('log-management.pattern', '*.log');
    }

    public function directory(): string
    {
        return $this->directory;
    }

    /**
     * Newest file first.
     *
     * @return Collection<int, LogFile>
     */
    public function all(): Collection
    {
        $paths = glob(rtrim($this->directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $this->pattern) ?: [];

        return collect($paths)
            ->filter(fn (string $path) => is_file($path) && is_readable($path))
            ->map(fn (string $path) => LogFile::fromPath($path))
            ->sortByDesc(fn (LogFile $file) => $file->modifiedAt)
            ->values();
    }

    /**
     * @return array<string, string> file name => picker label
     */
    public function options(): array
    {
        return $this->all()
            ->mapWithKeys(fn (LogFile $file) => [$file->name => $file->label()])
            ->all();
    }

    public function find(?string $name): ?LogFile
    {
        if (blank($name)) {
            return null;
        }

        // Compare on the basename only: the candidate set is built from the log
        // directory itself, so a traversal attempt simply matches nothing.
        $name = basename($name);

        return $this->all()->first(fn (LogFile $file) => $file->name === $name);
    }

    public function default(): ?LogFile
    {
        return $this->all()->first();
    }

    /**
     * Empty a log file without removing it, so the writer's open handle and the
     * file permissions survive.
     */
    public function clear(LogFile $file): bool
    {
        // Compare against false explicitly: a successful truncate writes zero
        // bytes, and file_put_contents returns that 0 rather than true.
        return @file_put_contents($file->path, '') !== false;
    }

    public function delete(LogFile $file): bool
    {
        return @unlink($file->path);
    }
}
