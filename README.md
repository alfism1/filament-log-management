# Filament Log Management

A Filament 3 log viewer that filters Laravel log entries by **the process that produced them** —
cron, queue, mail, database, and whatever domain processes you configure — on top of the usual
level / text / date filters.

- Reads every `*.log` in `storage/logs`, newest entry first
- Groups entries into configurable **processes**, with hit counts in the filter dropdowns
- Full-text search across message, context and stack trace
- Rows expand to show the full message, pretty-printed context and the stack trace
- Unwraps Laravel exception entries, whose trace is buried inside the JSON `exception` key
- Auto-refresh, download, empty and delete actions
- Never loads a whole file: it streams backwards from the end under a scan budget
- English and Indonesian translations included

## Requirements

| | |
| --- | --- |
| PHP | 8.2+ |
| Laravel | 11 / 12 |
| Filament | 3.2+ |

## Installation

```bash
composer require alfism1/filament-log-management
```

Register the plugin on the panel:

```php
use Alfism1\FilamentLogManagement\FilamentLogManagementPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugin(FilamentLogManagementPlugin::make());
}
```

That's it — the page appears under a **Developer** navigation group at `/admin/log-management`.

Publish the config to change anything:

```bash
php artisan vendor:publish --tag=filament-log-management-config
```

Views and translations are publishable too, with `--tag=filament-log-management-views` and
`--tag=filament-log-management-translations`.

## Configuring the plugin

Everything is optional; anything left unset falls back to the config file.

```php
FilamentLogManagementPlugin::make()
    ->navigationGroup('Developer')
    ->navigationSort(4)
    ->navigationIcon('heroicon-o-document-magnifying-glass')
    ->navigationLabel('Log Management')
    ->slug('logs')
    ->registerNavigation(fn () => app()->environment('local', 'staging'))
    ->authorize(fn () => auth()->user()->can('page_LogViewer'))
    ->usingPage(MyLogViewer::class)
```

## Processes — the point of the package

Every entry is classified into the **first** matching process, so config order matters: put specific
processes above general ones. A process matches when either

- **`files`** — the log file name matches one of its globs, or
- **`patterns`** — one of its regexes matches `"<message> <context>"`.

An explicit `[tag]` prefix on the message always wins over both:

```php
Log::info('[cron] nightly quota rebuild finished', ['trips' => $count]);
Log::error('[booking] refund failed', ['booking_id' => $booking->id]);
```

The tag is matched against every process key and its `aliases`. A tag matching nothing configured
still gets its own filter option, labelled from the tag — so `[midtrans-webhook]` shows up as
"Midtrans Webhook" with no config change at all.

### Adding your own

The shipped defaults cover framework concerns only (scheduler, queue, notification, mail, upload,
auth, database, http). Add your domain processes **above** them in the published config:

```php
'processes' => [

    'booking' => [
        'label' => 'Booking',
        'color' => 'primary',                  // Filament badge colour
        'icon' => 'heroicon-o-ticket',
        'aliases' => ['bookings'],             // also matched as a [tag]
        'files' => ['booking-*.log'],          // optional
        'patterns' => [
            '/\bbooking/i',                    // prose: "booking created"
            '/[a-z]Booking/',                  // identifiers: "saveBooking failed"
        ],
    ],

    // ... the shipped defaults follow
],
```

Domain nouns want that **pattern pair**: `/\bword/i` catches prose, `/[a-z]Word/` catches
camelCase identifiers quoted in the message. The camelCase form is also what stops `stripe` from
matching a `trip` process.

Order tips learned the hard way: put `invoice` and `payment` above `booking` (a message mentioning
both is usually about the more specific one), and a dedicated-channel process like `livechat` above
the generic one it would otherwise fall into.

## Scan depth

Log files grow without bound, so the file is never fully loaded. It is read backwards in 256 KB
chunks and parsed newest-first, stopping at the requested depth (default 2,000 entries), the
`max_bytes` cap, or the start of the file.

**Filters therefore apply to the last N entries, not to the whole file.** When the scan stops early
the page says so and points at the depth selector. Raising the depth costs a linear scan —
roughly 900 entries across 3.8 MB parses in ~150 ms.

## Authorization

By default any user who can reach the panel can open the page. To gate it, either set a Gate
ability in the config:

```php
'authorization' => [
    'permission' => 'page_LogViewer',
],
```

or pass a closure to the plugin, which overrides the config:

```php
->authorize(fn () => auth()->user()->hasRole('developer'))
```

### With Filament Shield

Shield discovers plugin-registered pages, so:

```bash
php artisan shield:generate --page=LogViewer
```

then set `'permission' => 'page_LogViewer'`, or use
`->authorize(fn () => auth()->user()->can('page_LogViewer'))`. `super_admin` passes either way
through Shield's `Gate::before`.

## Config reference

| Key | Default | Purpose |
| --- | --- | --- |
| `navigation.register` | `true` | Whether the menu item is registered. |
| `navigation.group` | `Developer` | Navigation group. |
| `navigation.sort` | `null` | Navigation sort. |
| `navigation.icon` | `heroicon-o-document-magnifying-glass` | Menu icon. |
| `navigation.label` | `null` | `null` = the translated default. |
| `navigation.slug` | `log-management` | URL segment. |
| `authorization.permission` | `null` | Gate ability required to view. `null` = anyone on the panel. |
| `path` | `storage_path('logs')` | Directory scanned. |
| `pattern` | `*.log` | Which files appear in the picker. |
| `scan_entries` | `2000` | Default scan depth. |
| `scan_options` | `[500, 2000, 10000, 50000]` | Choices in the depth selector. |
| `max_matches` | `5000` | Cap on matched entries held in memory. |
| `max_bytes` | 64 MB | Cap on bytes read per request. |
| `per_page` | `25` | Rows per page. |
| `poll_interval` | `10` | Seconds between auto-refresh polls. |
| `allow_delete` | `LOG_MANAGEMENT_ALLOW_DELETE`, `true` | Shows the empty/delete actions. |

Set `LOG_MANAGEMENT_ALLOW_DELETE=false` in production if log files should only ever be rotated by
the system.

## Under the hood

| Class | Role |
| --- | --- |
| `Support\LogFileRegistry` | Discovers log files; resolves a requested name against the discovered set, so a name from the browser can never escape the log directory. |
| `Support\LogReader` | Streams a file backwards in chunks, applying filters during the scan. |
| `Support\LogParser` | Splits a line into level / message / context / trace, bracket-matching from the end so braces in a message are not mistaken for JSON. |
| `Support\LogProcessResolver` | Classifies an entry into a process. |
| `Data\*` | Immutable value objects for entries, files, queries and scan results. |

## Testing

```bash
composer install
composer test
```

## License

MIT. See [LICENSE.md](LICENSE.md).
