<?php

use Alfism1\FilamentLogManagement\FilamentLogManagementPlugin;
use Alfism1\FilamentLogManagement\Pages\LogViewer;
use Livewire\Livewire;

beforeEach(function () {
    $this->writeLog('laravel.log', implode("\n", [
        '[2026-08-01 10:00:00] local.INFO: File uploaded successfully',
        '[2026-08-02 10:00:00] local.ERROR: Scheduled command finished',
    ]) . "\n");
});

it('renders with the newest file selected', function () {
    Livewire::test(LogViewer::class)
        ->assertSuccessful()
        ->assertSet('data.file', 'laravel.log')
        ->assertSee('Scheduled command finished')
        ->assertSee('File uploaded successfully');
});

it('filters by process', function () {
    Livewire::test(LogViewer::class)
        ->call('filterProcess', 'scheduler')
        ->assertSet('data.processes', ['scheduler'])
        ->assertSee('Scheduled command finished')
        ->assertDontSee('File uploaded successfully');
});

it('filters down to problem levels', function () {
    Livewire::test(LogViewer::class)
        ->call('showProblemsOnly')
        ->assertSee('Scheduled command finished')
        ->assertDontSee('File uploaded successfully');
});

it('resets the filters', function () {
    Livewire::test(LogViewer::class)
        ->call('filterProcess', 'scheduler')
        ->assertDontSee('File uploaded successfully')
        ->call('resetFilters')
        ->assertSet('data.processes', [])
        ->assertSee('File uploaded successfully');
});

it('expands and collapses an entry', function () {
    $component = Livewire::test(LogViewer::class);

    $id = $component->instance()->scan->entries[0]->id;

    $component->call('toggleEntry', $id)
        ->assertSet('expandedEntry', $id)
        ->call('toggleEntry', $id)
        ->assertSet('expandedEntry', null);
});

it('downloads the selected log file', function () {
    Livewire::test(LogViewer::class)
        ->set('data.file', 'laravel.log')
        ->callAction('download')
        ->assertFileDownloaded('laravel.log');
});

it('empties and deletes the selected file', function () {
    $this->writeLog('other.log', "[2026-08-01 10:00:00] local.INFO: kept\n");

    Livewire::test(LogViewer::class)
        ->set('data.file', 'laravel.log')
        ->callAction('clear')
        ->assertNotified()
        ->assertSuccessful();

    expect(file_get_contents($this->logPath . '/laravel.log'))->toBe('');

    Livewire::test(LogViewer::class)
        ->set('data.file', 'laravel.log')
        ->callAction('delete')
        ->assertSuccessful();

    expect(file_exists($this->logPath . '/laravel.log'))->toBeFalse()
        ->and(file_exists($this->logPath . '/other.log'))->toBeTrue();
});

it('hides the destructive actions when allow_delete is off', function () {
    config()->set('log-management.allow_delete', false);

    Livewire::test(LogViewer::class)
        ->assertActionHidden('clear')
        ->assertActionHidden('delete')
        ->assertActionVisible('download');
});

it('shows an empty state when there are no log files', function () {
    unlink($this->logPath . '/laravel.log');

    Livewire::test(LogViewer::class)
        ->assertSuccessful()
        ->assertSee('No log entries match');
});

it('toggles auto refresh', function () {
    Livewire::test(LogViewer::class)
        ->assertSet('autoRefresh', false)
        ->callAction('toggleAutoRefresh')
        ->assertSet('autoRefresh', true);
});

/*
|--------------------------------------------------------------------------
| Plugin API
|--------------------------------------------------------------------------
*/

it('falls back to the config file for its navigation', function () {
    config()->set('log-management.navigation.group', 'Tools');
    config()->set('log-management.navigation.sort', 7);
    config()->set('log-management.navigation.slug', 'logs');
    config()->set('log-management.navigation.icon', 'heroicon-o-bug-ant');

    expect(LogViewer::getNavigationGroup())->toBe('Tools')
        ->and(LogViewer::getNavigationSort())->toBe(7)
        ->and(LogViewer::getSlug())->toBe('logs')
        ->and(LogViewer::getNavigationIcon())->toBe('heroicon-o-bug-ant');
});

it('lets the plugin override the config', function () {
    config()->set('log-management.navigation.group', 'Tools');

    FilamentLogManagementPlugin::get()
        ->navigationGroup('Developer')
        ->navigationSort(2)
        ->navigationLabel('Server Logs');

    expect(LogViewer::getNavigationGroup())->toBe('Developer')
        ->and(LogViewer::getNavigationSort())->toBe(2)
        ->and(LogViewer::getNavigationLabel())->toBe('Server Logs');
});

it('is authorized by default', function () {
    expect(LogViewer::canAccess())->toBeTrue()
        ->and(LogViewer::shouldRegisterNavigation())->toBeTrue();
});

it('denies access when the configured permission is missing', function () {
    config()->set('log-management.authorization.permission', 'page_LogViewer');

    // Nobody is logged in, so the gate check cannot pass.
    expect(LogViewer::canAccess())->toBeFalse();
});

it('lets an authorize closure decide', function () {
    FilamentLogManagementPlugin::get()->authorize(fn () => false);

    expect(LogViewer::canAccess())->toBeFalse();

    FilamentLogManagementPlugin::get()->authorize(fn () => true);

    expect(LogViewer::canAccess())->toBeTrue();
});

it('can be hidden from the navigation', function () {
    FilamentLogManagementPlugin::get()->registerNavigation(false);

    expect(LogViewer::shouldRegisterNavigation())->toBeFalse();
});
