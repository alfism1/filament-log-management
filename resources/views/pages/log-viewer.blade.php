@php
    $scan = $this->scan;
    $entries = $this->entries();
    $file = $this->currentFile();
    $activeProcesses = collect($scan->processCounts)->take(8);
@endphp

{{-- Styles live in resources/dist/filament-log-management.css, not Tailwind utilities. --}}
<x-filament-panels::page>
    <div
        @if ($autoRefresh) wire:poll.{{ $this->pollInterval() }} @endif
        class="flm-page"
    >
        {{-- Filters --}}
        <form wire:submit.prevent>
            {{ $this->form }}
        </form>

        {{-- Summary --}}
        <div class="flm-summary">
            <x-filament::section compact>
                <div class="flm-stat-label">
                    {{ $this->trans('summary.entries') }}
                </div>
                <div class="flm-stat-value">
                    {{ number_format($scan->total()) }}
                </div>
                <div class="flm-stat-hint">
                    {{ $this->trans('summary.entries_hint', ['count' => number_format($scan->scannedEntries)]) }}
                </div>
            </x-filament::section>

            <x-filament::section compact>
                <div class="flm-stat-label">
                    {{ $this->trans('summary.problems') }}
                </div>
                <div @class([
                    'flm-stat-value',
                    'flm-stat-value-danger' => $scan->problemCount() > 0,
                ])>
                    {{ number_format($scan->problemCount()) }}
                </div>
                <div class="flm-stat-hint">
                    <x-filament::link tag="button" size="xs" wire:click="showProblemsOnly">
                        {{ $this->trans('summary.problems_action') }}
                    </x-filament::link>
                </div>
            </x-filament::section>

            <x-filament::section compact>
                <div class="flm-stat-label">
                    {{ $this->trans('summary.warnings') }}
                </div>
                <div class="flm-stat-value">
                    {{ number_format($scan->levelCounts['warning'] ?? 0) }}
                </div>
                <div class="flm-stat-hint">
                    {{ $this->trans('summary.warnings_hint') }}
                </div>
            </x-filament::section>

            <x-filament::section compact>
                <div class="flm-stat-label">
                    {{ $this->trans('summary.file') }}
                </div>
                <div class="flm-stat-value flm-stat-file" title="{{ $file?->name }}">
                    {{ $file?->name ?? '—' }}
                </div>
                <div class="flm-stat-hint">
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
            <div class="flm-processes">
                <span class="flm-processes-label">
                    {{ $this->trans('processes_label') }}
                </span>

                @foreach ($activeProcesses as $processKey => $count)
                    @php $process = $this->process($processKey); @endphp

                    <button type="button" class="flm-process-button" wire:click="filterProcess('{{ $processKey }}')">
                        <x-filament::badge :color="$process->color" :icon="$process->icon">
                            {{ $process->label }} ({{ number_format($count) }})
                        </x-filament::badge>
                    </button>
                @endforeach

                <x-filament::link tag="button" size="xs" wire:click="resetFilters">
                    {{ $this->trans('reset_filters') }}
                </x-filament::link>
            </div>
        @endif

        {{-- Scan notice --}}
        @if ($scan->truncated)
            <div class="flm-notice">
                <x-filament::icon icon="heroicon-m-information-circle" class="flm-notice-icon" />
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

            <div class="flm-entries">
                @forelse ($entries as $entry)
                    @php
                        $process = $this->process($entry->processKey);
                        $isExpanded = $expandedEntry === $entry->id;
                    @endphp

                    <div
                        wire:key="log-entry-{{ $entry->id }}"
                        @class([
                            'flm-entry',
                            'flm-entry-expanded' => $isExpanded,
                        ])
                    >
                        <button type="button" wire:click="toggleEntry('{{ $entry->id }}')" class="flm-entry-row">
                            <x-filament::icon
                                :icon="$isExpanded ? 'heroicon-m-chevron-down' : 'heroicon-m-chevron-right'"
                                class="flm-entry-chevron"
                            />

                            <div class="flm-entry-main">
                                <div class="flm-entry-meta">
                                    <x-filament::badge :color="$entry->color()" :icon="$entry->icon()" size="xs">
                                        {{ strtoupper($entry->level) }}
                                    </x-filament::badge>

                                    <x-filament::badge :color="$process->color" :icon="$process->icon" size="xs">
                                        {{ $process->label }}
                                    </x-filament::badge>

                                    <span class="flm-entry-time">
                                        {{ $entry->time() }}
                                    </span>

                                    <span class="flm-entry-env">
                                        {{ $entry->environment }}
                                    </span>
                                </div>

                                <p @class([
                                    'flm-entry-message',
                                    'flm-entry-message-problem' => $entry->isProblem(),
                                ])>
                                    {{ $isExpanded ? $entry->message : $entry->shortMessage() }}
                                </p>
                            </div>
                        </button>

                        @if ($isExpanded)
                            <div class="flm-entry-details">
                                @if ($entry->prettyContext())
                                    <div>
                                        <div class="flm-entry-details-label">
                                            {{ $this->trans('entry.context') }}
                                        </div>
                                        <pre class="flm-code">{{ $entry->prettyContext() }}</pre>
                                    </div>
                                @endif

                                @if ($entry->stackTrace)
                                    <div>
                                        <div class="flm-entry-details-label">
                                            {{ $this->trans('entry.stack_trace') }}
                                        </div>
                                        <pre class="flm-code flm-code-tall">{{ $entry->stackTrace }}</pre>
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
                                    class="flm-entry-actions"
                                >
                                    <div x-ref="payload" hidden>{{ $entry->time() }} {{ strtoupper($entry->level) }}: {{ $entry->message }}
{{ $entry->context }}
{{ $entry->stackTrace }}</div>

                                    <x-filament::button size="xs" color="gray" icon="heroicon-m-clipboard" x-on:click="copy()">
                                        <span x-text="copied ? @js($this->trans('entry.copied')) : @js($this->trans('entry.copy'))">{{ $this->trans('entry.copy') }}</span>
                                    </x-filament::button>

                                    @if ($entry->tag)
                                        <span class="flm-entry-tag">
                                            {{ $this->trans('entry.tag') }}: <code>[{{ $entry->tag }}]</code>
                                        </span>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="flm-empty">
                        <x-filament::icon icon="heroicon-o-document-magnifying-glass" class="flm-empty-icon" />
                        <p class="flm-empty-heading">
                            {{ $this->trans('empty.heading') }}
                        </p>
                        <p class="flm-empty-description">
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
