<?php

use Alfism1\FilamentLogManagement\Support\LogProcessResolver;

function resolveProcess(string $message, string $file = 'laravel.log', ?string $context = null): string
{
    $resolver = app(LogProcessResolver::class);

    return $resolver->resolve($message, $context, $file, LogProcessResolver::extractTag($message));
}

it('classifies entries against the shipped defaults', function (string $message, string $file, string $expected) {
    expect(resolveProcess($message, $file))->toBe($expected);
})->with([
    'explicit tag' => ['[queue] retrying', 'laravel.log', 'queue'],
    'tag alias' => ['[cron] nightly sync finished', 'laravel.log', 'scheduler'],
    'scheduled command' => ['Scheduled command finished', 'laravel.log', 'scheduler'],
    'queued job' => ['SendDocumentationLinkJob failed', 'laravel.log', 'queue'],
    'notification' => ['FCM Send Error: token expired', 'laravel.log', 'notification'],
    'mail' => ['Password reminder email sent', 'laravel.log', 'mail'],
    'auth' => ['User logged in', 'laravel.log', 'auth'],
    'database' => ['SQLSTATE[HY000]: General error', 'laravel.log', 'database'],
    'http' => ['NotFoundHttpException for /missing', 'laravel.log', 'http'],
    'upload' => ['File uploaded successfully', 'laravel.log', 'upload'],
    'unmatched' => ['Something entirely unrelated happened', 'laravel.log', 'app'],
]);

it('matches against the context as well as the message', function () {
    expect(resolveProcess('operation finished', 'laravel.log', '{"queue":"default"}'))->toBe('queue');
});

it('routes by file name when a process declares a glob', function () {
    config()->set('log-management.processes.livechat', [
        'label' => 'Live Chat',
        'files' => ['livechat-*.log'],
        'patterns' => [],
    ]);

    expect(resolveProcess('session_ready', 'livechat-2026-08-08.log'))->toBe('livechat')
        ->and(resolveProcess('session_ready', 'laravel.log'))->toBe('app');
});

it('lets an explicit tag beat both the file glob and the patterns', function () {
    config()->set('log-management.processes.livechat', [
        'label' => 'Live Chat',
        'files' => ['livechat-*.log'],
        'patterns' => [],
    ]);

    expect(resolveProcess('[cron] purge old chats', 'livechat-2026-08-08.log'))->toBe('scheduler');
});

it('gives an unknown tag its own process', function () {
    $resolver = app(LogProcessResolver::class);

    $key = $resolver->resolve('[midtrans-webhook] callback received', null, 'laravel.log', 'midtrans-webhook');

    expect($key)->toBe('midtrans-webhook')
        ->and($resolver->describe($key)->label)->toBe('Midtrans Webhook')
        ->and($resolver->describe($key)->color)->toBe('gray');
});

it('honours the order processes are configured in', function () {
    config()->set('log-management.processes', [
        'specific' => ['label' => 'Specific', 'patterns' => ['/\bfailed\b/i']],
        'general' => ['label' => 'General', 'patterns' => ['/\bjob\b/i']],
    ]);

    expect(resolveProcess('job failed'))->toBe('specific');

    config()->set('log-management.processes', [
        'general' => ['label' => 'General', 'patterns' => ['/\bjob\b/i']],
        'specific' => ['label' => 'Specific', 'patterns' => ['/\bfailed\b/i']],
    ]);

    expect(resolveProcess('job failed'))->toBe('general');
});

it('describes configured processes with their label, colour and icon', function () {
    $process = app(LogProcessResolver::class)->describe('scheduler');

    expect($process->key)->toBe('scheduler')
        ->and($process->label)->toBe('Cron / Scheduler')
        ->and($process->color)->toBe('info')
        ->and($process->icon)->toBe('heroicon-o-clock');
});

it('lists every configured process with the fallback last', function () {
    $configured = app(LogProcessResolver::class)->configured();

    expect(array_key_last($configured))->toBe('app')
        ->and($configured)->toHaveKeys(['scheduler', 'queue', 'mail', 'database']);
});
