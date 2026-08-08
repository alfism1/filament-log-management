<?php

/*
|--------------------------------------------------------------------------
| Filament Log Management
|--------------------------------------------------------------------------
|
| Publish with:
|   php artisan vendor:publish --tag=filament-log-management-config
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    |
    | Defaults for the page's place in the panel. Anything set on the plugin
    | (->navigationGroup(), ->navigationSort(), ...) wins over these.
    |
    */
    'navigation' => [
        'register' => true,
        'group' => 'Developer',
        'sort' => null,
        'icon' => 'heroicon-o-document-magnifying-glass',
        'label' => null,   // null = the translated default
        'slug' => 'log-management',
    ],

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    |
    | permission: a Gate ability every viewer must have. Leave null to let any
    | user who can reach the panel open the page.
    |
    | With bezhansalleh/filament-shield installed, set this to "page_LogViewer"
    | and run `php artisan shield:generate --page=LogViewer`.
    |
    | A ->authorize() closure on the plugin overrides this entirely.
    |
    */
    'authorization' => [
        'permission' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Files
    |--------------------------------------------------------------------------
    */
    'path' => storage_path('logs'),

    'pattern' => '*.log',

    /*
    |--------------------------------------------------------------------------
    | Reading guards
    |--------------------------------------------------------------------------
    |
    | Log files grow without bound, so the viewer never reads a whole file: it
    | streams backwards from the end (newest first) and stops at whichever of
    | these limits it hits first.
    |
    | scan_entries  default number of entries read per request
    | scan_options  selectable depths offered in the "Scan depth" filter
    | max_matches   hard cap on matched entries kept in memory
    | max_bytes     hard cap on bytes read from a single file per request
    |
    */
    'scan_entries' => 2000,

    'scan_options' => [500, 2000, 10000, 50000],

    'max_matches' => 5000,

    'max_bytes' => 64 * 1024 * 1024,

    'per_page' => 25,

    /*
    | Seconds between auto-refresh polls when the toggle on the page is on.
    */
    'poll_interval' => 10,

    /*
    | Whether the page may empty/delete log files.
    */
    'allow_delete' => env('LOG_MANAGEMENT_ALLOW_DELETE', true),

    /*
    |--------------------------------------------------------------------------
    | Processes
    |--------------------------------------------------------------------------
    |
    | The bucket every entry is filtered by. Each entry is classified into the
    | FIRST process that matches, so order is significant — put the specific
    | ones above the general ones.
    |
    | A process matches when either:
    |
    |   files    - the log file name matches one of these globs, or
    |   patterns - one of these regexes matches "<message> <context>".
    |
    | An explicit tag always wins over the patterns: prefixing a log message
    | with a bracket tag routes it directly, e.g.
    |
    |   Log::info('[cron] nightly sync done');
    |
    | The tag is matched against each process key and its "aliases". Unknown
    | tags still get their own filter option, discovered from the scanned file.
    |
    | These defaults cover framework-level concerns only. Add your own domain
    | processes (booking, invoice, subscription, ...) above them — see the
    | README for the recommended pattern shape.
    |
    */
    'processes' => [

        'scheduler' => [
            'label' => 'Cron / Scheduler',
            'color' => 'info',
            'icon' => 'heroicon-o-clock',
            'aliases' => ['cron', 'schedule'],
            'patterns' => [
                '/\bcron\b/i',
                '/\bschedul(?:e|ed|er|ing)\b/i',
            ],
        ],

        'queue' => [
            'label' => 'Queue / Jobs',
            'color' => 'warning',
            'icon' => 'heroicon-o-queue-list',
            'aliases' => ['job', 'jobs', 'worker'],
            'patterns' => [
                '/\b[A-Z][A-Za-z0-9]*Job\b/',
                '/\bqueue(?:d|able)?\b/i',
                '/\bhorizon\b/i',
            ],
        ],

        'notification' => [
            'label' => 'Notification',
            'color' => 'info',
            'icon' => 'heroicon-o-bell-alert',
            'aliases' => ['fcm', 'push'],
            'patterns' => [
                '/\bFCM\b/',
                '/push notification/i',
                '/\bnotification/i',
            ],
        ],

        'mail' => [
            'label' => 'Email',
            'color' => 'info',
            'icon' => 'heroicon-o-envelope',
            'aliases' => ['email'],
            'patterns' => [
                '/\bemail\b/i',
                '/\bmail(?:er|able|gun)?\b/i',
                '/\bsmtp\b/i',
            ],
        ],

        'upload' => [
            'label' => 'Upload / Media',
            'color' => 'gray',
            'icon' => 'heroicon-o-photo',
            'aliases' => ['media', 'file'],
            'patterns' => [
                '/\bupload(?:ed|ing)?\b/i',
                '/\bmedia\b/i',
            ],
        ],

        'auth' => [
            'label' => 'Auth',
            'color' => 'gray',
            'icon' => 'heroicon-o-key',
            'aliases' => ['login', 'security'],
            'patterns' => [
                '/\b(?:login|logout|logged in|logged out)\b/i',
                '/\bunauthenticated\b/i',
                '/\bAuthenticationException\b/',
                '/password reset/i',
            ],
        ],

        'database' => [
            'label' => 'Database',
            'color' => 'danger',
            'icon' => 'heroicon-o-circle-stack',
            'aliases' => ['db', 'sql'],
            'patterns' => [
                '/SQLSTATE/',
                '/QueryException/',
                '/\bdeadlock\b/i',
                '/\bmigrat(?:e|ion|ed)\b/i',
            ],
        ],

        'http' => [
            'label' => 'HTTP / Requests',
            'color' => 'gray',
            'icon' => 'heroicon-o-globe-alt',
            'aliases' => ['request', 'api'],
            'patterns' => [
                '/NotFoundHttpException/',
                '/MethodNotAllowedHttpException/',
                '/ValidationException/',
                '/\bcurl error\b/i',
                '/ConnectionException/',
            ],
        ],

    ],

    /*
    | Bucket for entries that match no process above.
    */
    'fallback' => [
        'key' => 'app',
        'label' => 'Application',
        'color' => 'gray',
        'icon' => 'heroicon-o-cube',
    ],

];
