<?php

use Alfism1\FilamentLogManagement\Support\LogParser;
use Alfism1\FilamentLogManagement\Support\LogProcessResolver;

it('parses a single line entry into its parts', function () {
    $entry = app(LogParser::class)->parse(
        '[2026-08-08 12:33:58] local.INFO: Documentation email sent successfully {"booking_id":1,"customer_email":"a@b.com"} []',
        [],
        'laravel.log',
        'id',
    );

    expect($entry)->not->toBeNull()
        ->and($entry->level)->toBe('info')
        ->and($entry->environment)->toBe('local')
        ->and($entry->message)->toBe('Documentation email sent successfully')
        ->and($entry->context)->toBe('{"booking_id":1,"customer_email":"a@b.com"}')
        ->and($entry->stackTrace)->toBeNull()
        ->and($entry->loggedAt->format('Y-m-d H:i:s'))->toBe('2026-08-08 12:33:58');
});

it('parses every psr-3 level', function (string $written, string $expected) {
    $entry = app(LogParser::class)->parse(
        "[2026-08-08 12:33:58] production.{$written}: something happened",
        [],
        'laravel.log',
        'id',
    );

    expect($entry->level)->toBe($expected);
})->with([
    ['DEBUG', 'debug'],
    ['INFO', 'info'],
    ['NOTICE', 'notice'],
    ['WARNING', 'warning'],
    ['ERROR', 'error'],
    ['CRITICAL', 'critical'],
    ['ALERT', 'alert'],
    ['EMERGENCY', 'emergency'],
]);

it('parses timestamps that carry microseconds and an offset', function () {
    $entry = app(LogParser::class)->parse(
        '[2026-08-08T12:33:58.123456+07:00] local.INFO: hello',
        [],
        'laravel.log',
        'id',
    );

    expect($entry->message)->toBe('hello')
        ->and($entry->loggedAt->format('Y-m-d H:i:s'))->toBe('2026-08-08 12:33:58');
});

it('ignores lines that are not log entries', function () {
    $entry = app(LogParser::class)->parse('#0 /app/foo.php(12): bar()', [], 'laravel.log', 'id');

    expect($entry)->toBeNull();
});

it('does not mistake braces inside the message for the context', function () {
    $entry = app(LogParser::class)->parse(
        '[2026-08-08 12:33:58] local.WARNING: Missing key [config] for {tenant} handler',
        [],
        'laravel.log',
        'id',
    );

    expect($entry->message)->toBe('Missing key [config] for {tenant} handler')
        ->and($entry->context)->toBeNull();
});

it('handles nested json and braces inside string values', function () {
    $entry = app(LogParser::class)->parse(
        '[2026-08-08 12:33:58] local.INFO: payload received {"body":{"template":"hi {name}","items":[1,2]},"ok":true}',
        [],
        'laravel.log',
        'id',
    );

    expect($entry->message)->toBe('payload received')
        ->and(json_decode($entry->context, true))->toBe([
            'body' => ['template' => 'hi {name}', 'items' => [1, 2]],
            'ok' => true,
        ]);
});

it('lifts a multi line exception context out into the stack trace', function () {
    $header = '[2026-08-08 15:16:40] local.ERROR: Trip generation failed {"booking_id":7,"exception":"[object] (RuntimeException(code: 0): Trip generation failed at /app/Jobs/GenerateTripJob.php:88)';

    $entry = app(LogParser::class)->parse(
        $header,
        [
            '[stacktrace]',
            // Backslashes arrive JSON-escaped, exactly as json_encode wrote them.
            '#0 /app/vendor/laravel/framework/src/Bus/Dispatcher.php(128): App\\\\Jobs\\\\GenerateTripJob->handle()',
            '#1 {main}',
            '"} ',
        ],
        'laravel.log',
        'id',
    );

    expect($entry->message)->toBe('Trip generation failed')
        ->and($entry->context)->toBe('{"booking_id":7}')
        ->and($entry->stackTrace)->toContain('[stacktrace]')
        ->and($entry->stackTrace)->toContain('GenerateTripJob->handle()')
        ->and($entry->stackTrace)->not->toContain('"}');
});

it('keeps plain trailing lines as the stack trace', function () {
    $entry = app(LogParser::class)->parse(
        '[2026-08-08 15:16:40] local.ERROR: Something broke {"id":1}',
        ['#0 /app/foo.php(12): bar()', '#1 {main}'],
        'laravel.log',
        'id',
    );

    expect($entry->context)->toBe('{"id":1}')
        ->and($entry->stackTrace)->toBe("#0 /app/foo.php(12): bar()\n#1 {main}");
});

it('pretty prints the context and leaves invalid json alone', function () {
    $entry = app(LogParser::class)->parse(
        '[2026-08-08 12:33:58] local.INFO: hello {"a":1}',
        [],
        'laravel.log',
        'id',
    );

    expect($entry->prettyContext())->toBe("{\n    \"a\": 1\n}");
});

it('extracts a bracket tag from the message', function () {
    expect(LogProcessResolver::extractTag('[livechat] session_start'))->toBe('livechat')
        ->and(LogProcessResolver::extractTag('no tag here'))->toBeNull()
        ->and(LogProcessResolver::extractTag('[a very very very long tag that goes past the limit and keeps going] x'))->toBeNull();
});
