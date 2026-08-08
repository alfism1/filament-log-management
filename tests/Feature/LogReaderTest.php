<?php

use Alfism1\FilamentLogManagement\Data\LogQuery;
use Alfism1\FilamentLogManagement\Support\LogFileRegistry;
use Alfism1\FilamentLogManagement\Support\LogReader;

function scan(array $filters = [], string $file = 'laravel.log'): \Alfism1\FilamentLogManagement\Data\LogScanResult
{
    return app(LogReader::class)->scan(
        app(LogFileRegistry::class)->find($file),
        LogQuery::fromArray($filters),
    );
}

it('reads entries newest first', function () {
    $this->writeLog('laravel.log', implode("\n", [
        '[2026-08-01 10:00:00] local.INFO: first',
        '[2026-08-02 10:00:00] local.INFO: second',
        '[2026-08-03 10:00:00] local.INFO: third',
    ]) . "\n");

    $result = scan();

    expect($result->scannedEntries)->toBe(3)
        ->and(array_map(fn ($entry) => $entry->message, $result->entries))
        ->toBe(['third', 'second', 'first']);
});

it('handles an empty file', function () {
    $this->writeLog('laravel.log', '');

    $result = scan();

    expect($result->scannedEntries)->toBe(0)
        ->and($result->entries)->toBe([])
        ->and($result->truncated)->toBeFalse();
});

it('filters by level, process, search and date', function () {
    $this->writeLog('laravel.log', implode("\n", [
        '[2026-08-01 10:00:00] local.INFO: File uploaded successfully',
        '[2026-08-02 10:00:00] local.ERROR: SendDocumentationLinkJob failed {"booking_id":9}',
        '[2026-08-03 10:00:00] local.ERROR: Scheduled command finished',
        '[2026-08-04 10:00:00] local.DEBUG: User logged in',
    ]) . "\n");

    expect(scan(['levels' => ['error']])->total())->toBe(2);

    $byProcess = scan(['processes' => ['scheduler']]);
    expect($byProcess->total())->toBe(1)
        ->and($byProcess->entries[0]->message)->toContain('Scheduled command');

    expect(scan(['search' => 'booking_id'])->total())->toBe(1);

    $byDate = scan(['from' => '2026-08-03', 'to' => '2026-08-03']);
    expect($byDate->total())->toBe(1)
        ->and($byDate->entries[0]->message)->toContain('Scheduled command');
});

it('searches the context and the stack trace too', function () {
    $this->writeLog('laravel.log', implode("\n", [
        '[2026-08-01 10:00:00] local.ERROR: broke {"order_id":"ABC-99"}',
        '#0 /app/Services/Payments.php(31): charge()',
        '[2026-08-02 10:00:00] local.INFO: fine',
    ]) . "\n");

    expect(scan(['search' => 'ABC-99'])->total())->toBe(1)
        ->and(scan(['search' => 'Payments.php'])->total())->toBe(1)
        ->and(scan(['search' => 'nothing here'])->total())->toBe(0);
});

it('counts levels and processes independently of the facet filters', function () {
    $this->writeLog('laravel.log', implode("\n", [
        '[2026-08-01 10:00:00] local.INFO: File uploaded successfully',
        '[2026-08-02 10:00:00] local.ERROR: SendDocumentationLinkJob failed',
        '[2026-08-03 10:00:00] local.ERROR: Scheduled command finished',
    ]) . "\n");

    $result = scan(['levels' => ['error']]);

    // Only the errors are listed, but the counts still describe the whole file
    // so the filter dropdowns keep showing every option.
    expect($result->total())->toBe(2)
        ->and($result->levelCounts)->toBe(['error' => 2, 'info' => 1])
        ->and($result->processCounts)->toHaveKeys(['upload', 'queue', 'scheduler'])
        ->and($result->problemCount())->toBe(2);
});

it('narrows the counts when a scope filter is applied', function () {
    $this->writeLog('laravel.log', implode("\n", [
        '[2026-08-01 10:00:00] local.INFO: alpha',
        '[2026-08-02 10:00:00] local.ERROR: beta',
    ]) . "\n");

    expect(scan(['search' => 'alpha'])->levelCounts)->toBe(['info' => 1]);
});

it('stops at the requested scan depth and says so', function () {
    $lines = [];

    for ($i = 1; $i <= 50; $i++) {
        $lines[] = sprintf('[2026-08-08 10:%02d:00] local.INFO: entry %d', $i % 60, $i);
    }

    $this->writeLog('laravel.log', implode("\n", $lines) . "\n");

    $result = scan(['depth' => 10]);

    expect($result->scannedEntries)->toBe(10)
        ->and($result->truncated)->toBeTrue()
        ->and($result->entries[0]->message)->toBe('entry 50')
        ->and($result->entries[9]->message)->toBe('entry 41');
});

it('reads entries that span a chunk boundary', function () {
    // A stack trace far larger than one read chunk must still be attached to
    // its header line rather than dropped.
    $trace = collect(range(1, 20000))
        ->map(fn (int $i) => "#{$i} /app/vendor/laravel/framework/src/Illuminate/Foundation/Application.php({$i}): handle()")
        ->implode("\n");

    $this->writeLog('laravel.log', implode("\n", [
        '[2026-08-01 10:00:00] local.INFO: before',
        '[2026-08-02 10:00:00] local.ERROR: exploded',
        $trace,
        '[2026-08-03 10:00:00] local.INFO: after',
    ]) . "\n");

    $result = scan();

    expect($result->scannedEntries)->toBe(3)
        ->and($result->entries[1]->message)->toBe('exploded')
        ->and($result->entries[1]->stackTrace)->toContain('#20000 ')
        ->and($result->entries[2]->message)->toBe('before');
});

it('paginates the matched entries', function () {
    $lines = [];

    for ($i = 1; $i <= 30; $i++) {
        $lines[] = sprintf('[2026-08-08 10:%02d:00] local.INFO: entry %d', $i, $i);
    }

    $this->writeLog('laravel.log', implode("\n", $lines) . "\n");

    $page = scan()->paginate(perPage: 10, page: 2);

    expect($page->total())->toBe(30)
        ->and($page->lastPage())->toBe(3)
        ->and($page->items()[0]->message)->toBe('entry 20');
});

it('only exposes log files inside the configured directory', function () {
    $this->writeLog('laravel.log', "[2026-08-01 10:00:00] local.INFO: hello\n");

    $registry = app(LogFileRegistry::class);

    expect($registry->find('laravel.log'))->not->toBeNull()
        ->and($registry->find('../../.env'))->toBeNull()
        ->and($registry->find('/etc/passwd'))->toBeNull()
        ->and($registry->find('missing.log'))->toBeNull()
        ->and($registry->find(null))->toBeNull();
});

it('lists log files newest first and honours the pattern', function () {
    $this->writeLog('old.log', "[2026-08-01 10:00:00] local.INFO: old\n");
    touch($this->logPath . '/old.log', time() - 3600);

    $this->writeLog('new.log', "[2026-08-02 10:00:00] local.INFO: new\n");
    $this->writeLog('notes.txt', 'ignored');

    $registry = app(LogFileRegistry::class);

    expect($registry->all()->pluck('name')->all())->toBe(['new.log', 'old.log'])
        ->and($registry->default()->name)->toBe('new.log')
        ->and($registry->options())->toHaveKeys(['new.log', 'old.log']);
});

it('empties and deletes files through the registry', function () {
    $this->writeLog('laravel.log', "[2026-08-01 10:00:00] local.INFO: disposable\n");

    $registry = app(LogFileRegistry::class);
    $file = $registry->find('laravel.log');

    expect($registry->clear($file))->toBeTrue()
        ->and(file_get_contents($file->path))->toBe('');

    expect($registry->delete($file))->toBeTrue()
        ->and(file_exists($file->path))->toBeFalse();
});
