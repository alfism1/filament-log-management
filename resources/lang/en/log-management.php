<?php

return [

    'title' => 'Log Management',
    'navigation_label' => 'Log Management',

    'filters' => [
        'file' => [
            'label' => 'Log file',
            'placeholder' => 'No log files',
        ],
        'processes' => [
            'label' => 'Process',
            'placeholder' => 'All processes',
            'helper' => 'Cron, queue, mail, database, ...',
        ],
        'levels' => [
            'label' => 'Level',
            'placeholder' => 'All levels',
        ],
        'search' => [
            'label' => 'Search',
            'placeholder' => 'Message, context, or stack trace',
        ],
        'from' => [
            'label' => 'From date',
        ],
        'to' => [
            'label' => 'To date',
        ],
        'depth' => [
            'label' => 'Scan depth',
            'helper' => 'How many trailing lines are read',
            'option' => 'last :count lines',
        ],
    ],

    'actions' => [
        'auto_refresh' => [
            'on' => 'Auto refresh: ON',
            'off' => 'Auto refresh: OFF',
        ],
        'refresh' => 'Refresh',
        'download' => 'Download',
        'clear' => [
            'label' => 'Empty',
            'heading' => 'Empty this log file?',
            'description' => 'Everything in :file will be removed and cannot be recovered.',
        ],
        'delete' => [
            'label' => 'Delete file',
            'heading' => 'Delete this log file?',
            'description' => ':file will be permanently removed from the server.',
        ],
    ],

    'notifications' => [
        'cleared' => [
            'title' => 'Log file emptied',
            'body' => ':file has been emptied.',
        ],
        'clear_failed' => [
            'title' => 'Could not empty the log file',
            'body' => 'Check write permissions on :path.',
        ],
        'deleted' => [
            'title' => 'Log file deleted',
            'body' => ':file has been deleted.',
        ],
        'delete_failed' => [
            'title' => 'Could not delete the log file',
            'body' => 'Check write permissions on the log directory.',
        ],
    ],

    'summary' => [
        'entries' => 'Entries shown',
        'entries_hint' => 'of :count lines scanned',
        'problems' => 'Errors & critical',
        'problems_action' => 'Show errors only',
        'warnings' => 'Warnings',
        'warnings_hint' => 'warning level',
        'file' => 'File',
        'file_empty' => 'No log files',
        'file_hint' => ':size · updated :time',
    ],

    'processes_label' => 'Processes:',
    'reset_filters' => 'Reset filters',

    'truncated' => 'Scanning stopped after the last :count lines (:size read). Older entries were not filtered — raise the <strong>Scan depth</strong> if you need them.',

    'entries' => [
        'heading' => 'Log entries',
        'description' => 'Click a row to see the full message, context and stack trace.',
    ],

    'entry' => [
        'context' => 'Context',
        'stack_trace' => 'Stack trace',
        'copy' => 'Copy entry',
        'copied' => 'Copied',
        'tag' => 'tag',
    ],

    'empty' => [
        'heading' => 'No log entries match',
        'description' => 'Change the filters, raise the scan depth, or pick another log file.',
        'description_no_files' => 'There are no log files in :path yet.',
    ],

];
