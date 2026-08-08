<?php

namespace Alfism1\FilamentLogManagement\Support;

/**
 * The PSR-3 levels Monolog writes into the log files, plus the presentation
 * bits (badge colour, icon) and the severity ordering the viewer filters on.
 */
final class LogLevel
{
    /**
     * Severity, highest first. Also the order levels are listed in the filter.
     *
     * @var array<string, int>
     */
    public const SEVERITY = [
        'emergency' => 8,
        'alert' => 7,
        'critical' => 6,
        'error' => 5,
        'warning' => 4,
        'notice' => 3,
        'info' => 2,
        'debug' => 1,
    ];

    /** @var array<string, string> */
    public const COLORS = [
        'emergency' => 'danger',
        'alert' => 'danger',
        'critical' => 'danger',
        'error' => 'danger',
        'warning' => 'warning',
        'notice' => 'info',
        'info' => 'info',
        'debug' => 'gray',
    ];

    /** @var array<string, string> */
    public const ICONS = [
        'emergency' => 'heroicon-m-fire',
        'alert' => 'heroicon-m-bell-alert',
        'critical' => 'heroicon-m-exclamation-triangle',
        'error' => 'heroicon-m-x-circle',
        'warning' => 'heroicon-m-exclamation-circle',
        'notice' => 'heroicon-m-information-circle',
        'info' => 'heroicon-m-information-circle',
        'debug' => 'heroicon-m-bug-ant',
    ];

    /**
     * The levels considered "a problem", used by the errors-only quick filter.
     *
     * @var list<string>
     */
    public const PROBLEM_LEVELS = ['emergency', 'alert', 'critical', 'error'];

    public static function normalize(string $level): string
    {
        $level = strtolower(trim($level));

        return isset(self::SEVERITY[$level]) ? $level : 'info';
    }

    public static function color(string $level): string
    {
        return self::COLORS[strtolower($level)] ?? 'gray';
    }

    public static function icon(string $level): string
    {
        return self::ICONS[strtolower($level)] ?? 'heroicon-m-information-circle';
    }

    /**
     * @return array<string, string> level => label, severity descending
     */
    public static function options(): array
    {
        $options = [];

        foreach (array_keys(self::SEVERITY) as $level) {
            $options[$level] = ucfirst($level);
        }

        return $options;
    }
}
