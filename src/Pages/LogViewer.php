<?php

namespace Alfism1\FilamentLogManagement\Pages;

use Alfism1\FilamentLogManagement\Data\LogFile;
use Alfism1\FilamentLogManagement\Data\LogProcess;
use Alfism1\FilamentLogManagement\Data\LogQuery;
use Alfism1\FilamentLogManagement\Data\LogScanResult;
use Alfism1\FilamentLogManagement\FilamentLogManagementPlugin;
use Alfism1\FilamentLogManagement\Support\LogFileRegistry;
use Alfism1\FilamentLogManagement\Support\LogLevel;
use Alfism1\FilamentLogManagement\Support\LogProcessResolver;
use Alfism1\FilamentLogManagement\Support\LogReader;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Response;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Browses the files Laravel writes into storage/logs, grouping every entry into
 * a "process" (cron, queue, mail, ...) so a log can be filtered by what
 * produced it as well as by severity, text and date.
 *
 * Classification rules live in config/log-management.php.
 */
class LogViewer extends Page implements HasForms
{
    use InteractsWithForms;
    use WithPagination;

    protected static string $view = 'filament-log-management::pages.log-viewer';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public bool $autoRefresh = false;

    public ?string $expandedEntry = null;

    /*
    |--------------------------------------------------------------------------
    | Panel integration
    |--------------------------------------------------------------------------
    */

    public static function plugin(): FilamentLogManagementPlugin
    {
        return FilamentLogManagementPlugin::get();
    }

    public static function getNavigationGroup(): ?string
    {
        return static::plugin()->getNavigationGroup();
    }

    public static function getNavigationSort(): ?int
    {
        return static::plugin()->getNavigationSort();
    }

    public static function getNavigationIcon(): string | Htmlable | null
    {
        return static::plugin()->getNavigationIcon();
    }

    public static function getNavigationLabel(): string
    {
        return static::plugin()->getNavigationLabel();
    }

    public static function getSlug(): string
    {
        return static::plugin()->getSlug();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::plugin()->shouldRegisterNavigation();
    }

    public static function canAccess(): bool
    {
        return static::plugin()->isAuthorized();
    }

    public function getTitle(): string
    {
        return __('filament-log-management::log-management.title');
    }

    public function mount(): void
    {
        $this->form->fill([
            'file' => $this->registry()->default()?->name,
            'processes' => [],
            'levels' => [],
            'search' => null,
            'from' => null,
            'to' => null,
            'depth' => (int) config('log-management.scan_entries', 2000),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Filters
    |--------------------------------------------------------------------------
    */

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()
                    ->compact()
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2, 'xl' => 4])
                            ->schema([
                                Select::make('file')
                                    ->label($this->trans('filters.file.label'))
                                    ->options(fn () => $this->registry()->options())
                                    ->searchable()
                                    ->native(false)
                                    ->live()
                                    ->afterStateUpdated(fn () => $this->onFilterChanged())
                                    ->placeholder($this->trans('filters.file.placeholder')),

                                Select::make('processes')
                                    ->label($this->trans('filters.processes.label'))
                                    ->multiple()
                                    ->options(fn () => $this->processOptions())
                                    ->searchable()
                                    ->native(false)
                                    ->live()
                                    ->afterStateUpdated(fn () => $this->onFilterChanged())
                                    ->placeholder($this->trans('filters.processes.placeholder'))
                                    ->helperText($this->trans('filters.processes.helper')),

                                Select::make('levels')
                                    ->label($this->trans('filters.levels.label'))
                                    ->multiple()
                                    ->options(fn () => $this->levelOptions())
                                    ->native(false)
                                    ->live()
                                    ->afterStateUpdated(fn () => $this->onFilterChanged())
                                    ->placeholder($this->trans('filters.levels.placeholder')),

                                TextInput::make('search')
                                    ->label($this->trans('filters.search.label'))
                                    ->placeholder($this->trans('filters.search.placeholder'))
                                    ->prefixIcon('heroicon-m-magnifying-glass')
                                    ->live(debounce: 500)
                                    ->afterStateUpdated(fn () => $this->onFilterChanged()),

                                DatePicker::make('from')
                                    ->label($this->trans('filters.from.label'))
                                    ->native(false)
                                    ->live()
                                    ->afterStateUpdated(fn () => $this->onFilterChanged()),

                                DatePicker::make('to')
                                    ->label($this->trans('filters.to.label'))
                                    ->native(false)
                                    ->live()
                                    ->afterStateUpdated(fn () => $this->onFilterChanged()),

                                Select::make('depth')
                                    ->label($this->trans('filters.depth.label'))
                                    ->options(fn () => $this->depthOptions())
                                    ->native(false)
                                    ->live()
                                    ->afterStateUpdated(fn () => $this->onFilterChanged())
                                    ->helperText($this->trans('filters.depth.helper')),
                            ]),
                    ]),
            ])
            ->statePath('data');
    }

    /**
     * Filters change which entries exist, so the current page number no longer
     * means anything — and an expanded row may have filtered itself away.
     */
    protected function onFilterChanged(): void
    {
        $this->resetPage();
        $this->expandedEntry = null;

        unset($this->scan);
    }

    public function resetFilters(): void
    {
        $this->form->fill([
            'file' => $this->data['file'] ?? $this->registry()->default()?->name,
            'processes' => [],
            'levels' => [],
            'search' => null,
            'from' => null,
            'to' => null,
            'depth' => (int) config('log-management.scan_entries', 2000),
        ]);

        $this->onFilterChanged();
    }

    /**
     * Quick filter: only the levels that represent a failure.
     */
    public function showProblemsOnly(): void
    {
        $this->data['levels'] = LogLevel::PROBLEM_LEVELS;

        $this->onFilterChanged();
    }

    public function filterProcess(string $processKey): void
    {
        $this->data['processes'] = [$processKey];

        $this->onFilterChanged();
    }

    public function toggleEntry(string $id): void
    {
        $this->expandedEntry = $this->expandedEntry === $id ? null : $id;
    }

    /*
    |--------------------------------------------------------------------------
    | Header actions
    |--------------------------------------------------------------------------
    */

    protected function getHeaderActions(): array
    {
        return [
            Action::make('toggleAutoRefresh')
                ->label(fn () => $this->trans($this->autoRefresh ? 'actions.auto_refresh.on' : 'actions.auto_refresh.off'))
                ->icon(fn () => $this->autoRefresh ? 'heroicon-m-pause-circle' : 'heroicon-m-play-circle')
                ->color(fn () => $this->autoRefresh ? 'success' : 'gray')
                ->action(function () {
                    $this->autoRefresh = ! $this->autoRefresh;

                    unset($this->scan);
                }),

            Action::make('refresh')
                ->label($this->trans('actions.refresh'))
                ->icon('heroicon-m-arrow-path')
                ->color('gray')
                ->action(function () {
                    unset($this->scan);
                }),

            Action::make('download')
                ->label($this->trans('actions.download'))
                ->icon('heroicon-m-arrow-down-tray')
                ->color('gray')
                ->visible(fn () => $this->currentFile() !== null)
                ->action(fn (): ?BinaryFileResponse => $this->downloadCurrentFile()),

            Action::make('clear')
                ->label($this->trans('actions.clear.label'))
                ->icon('heroicon-m-backspace')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading($this->trans('actions.clear.heading'))
                ->modalDescription(fn () => $this->trans('actions.clear.description', ['file' => $this->currentFile()?->name ?? '-']))
                ->visible(fn () => $this->canModifyFiles() && $this->currentFile() !== null)
                ->action(fn () => $this->clearCurrentFile()),

            Action::make('delete')
                ->label($this->trans('actions.delete.label'))
                ->icon('heroicon-m-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading($this->trans('actions.delete.heading'))
                ->modalDescription(fn () => $this->trans('actions.delete.description', ['file' => $this->currentFile()?->name ?? '-']))
                ->visible(fn () => $this->canModifyFiles() && $this->currentFile() !== null)
                ->action(fn () => $this->deleteCurrentFile()),
        ];
    }

    public function canModifyFiles(): bool
    {
        return (bool) config('log-management.allow_delete', true);
    }

    protected function downloadCurrentFile(): ?BinaryFileResponse
    {
        $file = $this->currentFile();

        if ($file === null) {
            return null;
        }

        return Response::download($file->path, $file->name);
    }

    protected function clearCurrentFile(): void
    {
        $file = $this->currentFile();

        if ($file === null) {
            return;
        }

        if ($this->registry()->clear($file)) {
            Notification::make()
                ->title($this->trans('notifications.cleared.title'))
                ->body($this->trans('notifications.cleared.body', ['file' => $file->name]))
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title($this->trans('notifications.clear_failed.title'))
                ->body($this->trans('notifications.clear_failed.body', ['path' => $file->path]))
                ->danger()
                ->send();
        }

        $this->onFilterChanged();
    }

    protected function deleteCurrentFile(): void
    {
        $file = $this->currentFile();

        if ($file === null) {
            return;
        }

        if ($this->registry()->delete($file)) {
            Notification::make()
                ->title($this->trans('notifications.deleted.title'))
                ->body($this->trans('notifications.deleted.body', ['file' => $file->name]))
                ->success()
                ->send();

            $this->data['file'] = $this->registry()->default()?->name;
        } else {
            Notification::make()
                ->title($this->trans('notifications.delete_failed.title'))
                ->body($this->trans('notifications.delete_failed.body'))
                ->danger()
                ->send();
        }

        $this->onFilterChanged();
    }

    /*
    |--------------------------------------------------------------------------
    | Reading
    |--------------------------------------------------------------------------
    */

    public function registry(): LogFileRegistry
    {
        return app(LogFileRegistry::class);
    }

    public function resolver(): LogProcessResolver
    {
        return app(LogProcessResolver::class);
    }

    public function currentFile(): ?LogFile
    {
        return $this->registry()->find($this->data['file'] ?? null)
            ?? $this->registry()->default();
    }

    /**
     * The scan backing this render. Computed so the file is read once per
     * request even though the filters, the summary and the list all need it.
     */
    #[Computed]
    public function scan(): LogScanResult
    {
        $file = $this->currentFile();

        if ($file === null) {
            return LogScanResult::empty();
        }

        return app(LogReader::class)->scan($file, LogQuery::fromArray($this->data ?? []));
    }

    public function entries(): LengthAwarePaginator
    {
        return $this->scan->paginate(
            perPage: (int) config('log-management.per_page', 25),
            page: $this->getPage(),
        );
    }

    public function process(string $key): LogProcess
    {
        return $this->resolver()->describe($key);
    }

    public function pollInterval(): string
    {
        return max(2, (int) config('log-management.poll_interval', 10)) . 's';
    }

    /**
     * @param  array<string, mixed>  $replace
     */
    public function trans(string $key, array $replace = []): string
    {
        return __('filament-log-management::log-management.' . $key, $replace);
    }

    /*
    |--------------------------------------------------------------------------
    | Filter options
    |--------------------------------------------------------------------------
    */

    /**
     * Configured processes first, then any extra ones discovered in the file
     * (an unrecognised "[tag]" prefix), each with its hit count in the scan.
     *
     * @return array<string, string>
     */
    protected function processOptions(): array
    {
        $counts = $this->scan->processCounts;

        $keys = array_unique([
            ...array_keys($this->resolver()->configured()),
            ...array_keys($counts),
        ]);

        $options = [];

        foreach ($keys as $key) {
            $count = $counts[$key] ?? 0;

            $options[$key] = $this->process($key)->label . ($count > 0 ? " ({$count})" : '');
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    protected function levelOptions(): array
    {
        $counts = $this->scan->levelCounts;

        $options = [];

        foreach (LogLevel::options() as $level => $label) {
            $count = $counts[$level] ?? 0;

            $options[$level] = $label . ($count > 0 ? " ({$count})" : '');
        }

        return $options;
    }

    /**
     * @return array<int, string>
     */
    protected function depthOptions(): array
    {
        $options = [];

        foreach ((array) config('log-management.scan_options', [500, 2000, 10000]) as $option) {
            $options[(int) $option] = $this->trans('filters.depth.option', [
                'count' => number_format((int) $option),
            ]);
        }

        return $options;
    }
}
