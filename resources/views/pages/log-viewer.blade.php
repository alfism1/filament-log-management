@php
    $scan = $this->scan;
    $entries = $this->entries();
    $file = $this->currentFile();
    $activeProcesses = collect($scan->processCounts)->take(8);
@endphp

<x-filament-panels::page>
    <div
        @if ($autoRefresh) wire:poll.{{ $this->pollInterval() }} @endif
        class="space-y-6"
    >
        {{-- Filters --}}
        <form wire:submit.prevent>
            {{ $this->form }}
        </form>

        {{-- Summary --}}
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <x-filament::section compact>
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    {{ $this->trans('summary.entries') }}
                </div>
                <div class="mt-1 text-2xl font-semibold tabular-nums text-gray-950 dark:text-white">
                    {{ number_format($scan->total()) }}
                </div>
                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    {{ $this->trans('summary.entries_hint', ['count' => number_format($scan->scannedEntries)]) }}
                </div>
            </x-filament::section>

            <x-filament::section compact>
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    {{ $this->trans('summary.problems') }}
                </div>
                <div @class([
                    'mt-1 text-2xl font-semibold tabular-nums',
                    'text-danger-600 dark:text-danger-400' => $scan->problemCount() > 0,
                    'text-gray-950 dark:text-white' => $scan->problemCount() === 0,
                ])>
                    {{ number_format($scan->problemCount()) }}
                </div>
                <button
                    type="button"
                    wire:click="showProblemsOnly"
                    class="mt-1 text-xs font-medium text-primary-600 hover:underline dark:text-primary-400"
                >
                    {{ $this->trans('summary.problems_action') }}
                </button>
            </x-filament::section>

            <x-filament::section compact>
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    {{ $this->trans('summary.warnings') }}
                </div>
                <div class="mt-1 text-2xl font-semibold tabular-nums text-gray-950 dark:text-white">
                    {{ number_format($scan->levelCounts['warning'] ?? 0) }}
                </div>
                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    {{ $this->trans('summary.warnings_hint') }}
                </div>
            </x-filament::section>

            <x-filament::section compact>
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    {{ $this->trans('summary.file') }}
                </div>
                <div class="mt-1 truncate font-mono text-sm font-semibold text-gray-950 dark:text-white" title="{{ $file?->name }}">
                    {{ $file?->name ?? '—' }}
                </div>
                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    @if ($file)
                        {{ $this->trans('summary.file_hint', [
                            'size' => $file->humanSize(),
                            'time' => $file->modified()->diffForHumans(),
                        ]) }}
                    @else
                        {{ $this->trans('summary.file_empty') }}
                    @endif
                </div>
            </x-filament::section>
        </div>

        {{-- Process shortcuts --}}
        @if ($activeProcesses->isNotEmpty())
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-sm font-medium text-gray-500 dark:text-gray-400">
                    {{ $this->trans('processes_label') }}
                </span>

                @foreach ($activeProcesses as $processKey => $count)
                    @php $process = $this->process($processKey); @endphp

                    <button type="button" wire:click="filterProcess('{{ $processKey }}')">
                        <x-filament::badge :color="$process->color" :icon="$process->icon">
                            {{ $process->label }} ({{ number_format($count) }})
                        </x-filament::badge>
                    </button>
                @endforeach

                <button
                    type="button"
                    wire:click="resetFilters"
                    class="text-xs font-medium text-primary-600 hover:underline dark:text-primary-400"
                >
                    {{ $this->trans('reset_filters') }}
                </button>
            </div>
        @endif

        {{-- Scan notice --}}
        @if ($scan->truncated)
            <div class="flex items-start gap-2 rounded-lg bg-warning-50 p-3 text-sm text-warning-700 ring-1 ring-warning-600/20 dark:bg-warning-400/10 dark:text-warning-400 dark:ring-warning-400/30">
                <x-filament::icon icon="heroicon-m-information-circle" class="mt-0.5 h-5 w-5 shrink-0" />
                <span>
                    {!! $this->trans('truncated', [
                        'count' => number_format($scan->scannedEntries),
                        'size' => \Illuminate\Support\Number::fileSize($scan->bytesRead, precision: 1),
                    ]) !!}
                </span>
            </div>
        @endif

        {{-- Entries --}}
        <x-filament::section compact>
            <x-slot name="heading">{{ $this->trans('entries.heading') }}</x-slot>

            <x-slot name="description">{{ $this->trans('entries.description') }}</x-slot>

            <div class="-mx-4 divide-y divide-gray-200 sm:-mx-6 dark:divide-white/10">
                @forelse ($entries as $entry)
                    @php
                        $process = $this->process($entry->processKey);
                        $isExpanded = $expandedEntry === $entry->id;
                    @endphp

                    <div wire:key="log-entry-{{ $entry->id }}">
                        <button
                            type="button"
                            wire:click="toggleEntry('{{ $entry->id }}')"
                            @class([
                                'flex w-full items-start gap-3 px-4 py-3 text-left transition hover:bg-gray-50 sm:px-6 dark:hover:bg-white/5',
                                'bg-gray-50 dark:bg-white/5' => $isExpanded,
                            ])
                        >
                            <x-filament::icon
                                :icon="$isExpanded ? 'heroicon-m-chevron-down' : 'heroicon-m-chevron-right'"
                                class="mt-0.5 h-4 w-4 shrink-0 text-gray-400"
                            />

                            <div class="min-w-0 flex-1 space-y-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <x-filament::badge :color="$entry->color()" :icon="$entry->icon()" size="xs">
                                        {{ strtoupper($entry->level) }}
                                    </x-filament::badge>

                                    <x-filament::badge :color="$process->color" :icon="$process->icon" size="xs">
                                        {{ $process->label }}
                                    </x-filament::badge>

                                    <span class="font-mono text-xs text-gray-500 dark:text-gray-400">
                                        {{ $entry->time() }}
                                    </span>

                                    <span class="text-xs text-gray-400 dark:text-gray-500">
                                        {{ $entry->environment }}
                                    </span>
                                </div>

                                <p @class([
                                    'break-words text-sm',
                                    'font-medium text-danger-600 dark:text-danger-400' => $entry->isProblem(),
                                    'text-gray-700 dark:text-gray-300' => ! $entry->isProblem(),
                                ])>
                                    {{ $isExpanded ? $entry->message : $entry->shortMessage() }}
                                </p>
                            </div>
                        </button>

                        @if ($isExpanded)
                            <div class="space-y-3 bg-gray-50 px-4 pb-4 sm:px-6 dark:bg-white/5">
                                @if ($entry->prettyContext())
                                    <div>
                                        <div class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            {{ $this->trans('entry.context') }}
                                        </div>
                                        <pre class="max-h-80 overflow-auto rounded-lg bg-gray-950 p-3 text-xs leading-relaxed text-gray-100">{{ $entry->prettyContext() }}</pre>
                                    </div>
                                @endif

                                @if ($entry->stackTrace)
                                    <div>
                                        <div class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            {{ $this->trans('entry.stack_trace') }}
                                        </div>
                                        <pre class="max-h-96 overflow-auto rounded-lg bg-gray-950 p-3 text-xs leading-relaxed text-gray-100">{{ $entry->stackTrace }}</pre>
                                    </div>
                                @endif

                                <div
                                    x-data="{
                                        copied: false,
                                        copy() {
                                            navigator.clipboard.writeText(this.$refs.payload.innerText).then(() => {
                                                this.copied = true
                                                setTimeout(() => (this.copied = false), 2000)
                                            })
                                        },
                                    }"
                                    class="flex items-center gap-3"
                                >
                                    <div x-ref="payload" class="hidden">{{ $entry->time() }} {{ strtoupper($entry->level) }}: {{ $entry->message }}
{{ $entry->context }}
{{ $entry->stackTrace }}</div>

                                    <x-filament::button size="xs" color="gray" icon="heroicon-m-clipboard" x-on:click="copy()">
                                        <span x-text="copied ? @js($this->trans('entry.copied')) : @js($this->trans('entry.copy'))">{{ $this->trans('entry.copy') }}</span>
                                    </x-filament::button>

                                    @if ($entry->tag)
                                        <span class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $this->trans('entry.tag') }}: <code>[{{ $entry->tag }}]</code>
                                        </span>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="px-4 py-12 text-center sm:px-6">
                        <x-filament::icon
                            icon="heroicon-o-document-magnifying-glass"
                            class="mx-auto h-10 w-10 text-gray-400 dark:text-gray-500"
                        />
                        <p class="mt-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ $this->trans('empty.heading') }}
                        </p>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            @if ($file)
                                {{ $this->trans('empty.description') }}
                            @else
                                {{ $this->trans('empty.description_no_files', ['path' => $this->registry()->directory()]) }}
                            @endif
                        </p>
                    </div>
                @endforelse
            </div>
        </x-filament::section>

        @if ($entries->hasPages())
            <x-filament::pagination :paginator="$entries" />
        @endif
    </div>
</x-filament-panels::page>
