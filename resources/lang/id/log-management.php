<?php

return [

    'title' => 'Log Management',
    'navigation_label' => 'Log Management',

    'filters' => [
        'file' => [
            'label' => 'Berkas log',
            'placeholder' => 'Tidak ada berkas log',
        ],
        'processes' => [
            'label' => 'Proses',
            'placeholder' => 'Semua proses',
            'helper' => 'Cron, queue, email, database, ...',
        ],
        'levels' => [
            'label' => 'Level',
            'placeholder' => 'Semua level',
        ],
        'search' => [
            'label' => 'Cari',
            'placeholder' => 'Pesan, context, atau stack trace',
        ],
        'from' => [
            'label' => 'Dari tanggal',
        ],
        'to' => [
            'label' => 'Sampai tanggal',
        ],
        'depth' => [
            'label' => 'Kedalaman pemindaian',
            'helper' => 'Jumlah baris terakhir yang dibaca',
            'option' => ':count baris terakhir',
        ],
    ],

    'actions' => [
        'auto_refresh' => [
            'on' => 'Auto refresh: ON',
            'off' => 'Auto refresh: OFF',
        ],
        'refresh' => 'Muat ulang',
        'download' => 'Unduh',
        'clear' => [
            'label' => 'Kosongkan',
            'heading' => 'Kosongkan berkas log?',
            'description' => 'Seluruh isi :file akan dihapus dan tidak dapat dikembalikan.',
        ],
        'delete' => [
            'label' => 'Hapus berkas',
            'heading' => 'Hapus berkas log?',
            'description' => ':file akan dihapus permanen dari server.',
        ],
    ],

    'notifications' => [
        'cleared' => [
            'title' => 'Berkas log dikosongkan',
            'body' => ':file berhasil dikosongkan.',
        ],
        'clear_failed' => [
            'title' => 'Gagal mengosongkan berkas log',
            'body' => 'Periksa izin tulis pada :path.',
        ],
        'deleted' => [
            'title' => 'Berkas log dihapus',
            'body' => ':file berhasil dihapus.',
        ],
        'delete_failed' => [
            'title' => 'Gagal menghapus berkas log',
            'body' => 'Periksa izin tulis pada direktori log.',
        ],
    ],

    'summary' => [
        'entries' => 'Entri tampil',
        'entries_hint' => 'dari :count baris dipindai',
        'problems' => 'Error & kritikal',
        'problems_action' => 'Tampilkan error saja',
        'warnings' => 'Peringatan',
        'warnings_hint' => 'level warning',
        'file' => 'Berkas',
        'file_empty' => 'Tidak ada berkas log',
        'file_hint' => ':size · diperbarui :time',
    ],

    'processes_label' => 'Proses:',
    'reset_filters' => 'Reset filter',

    'truncated' => 'Pemindaian dihentikan setelah :count baris terakhir (:size dibaca). Entri yang lebih lama tidak ikut difilter — naikkan <strong>Kedalaman pemindaian</strong> bila perlu.',

    'entries' => [
        'heading' => 'Entri log',
        'description' => 'Klik satu baris untuk melihat pesan lengkap, context, dan stack trace.',
    ],

    'entry' => [
        'context' => 'Context',
        'stack_trace' => 'Stack trace',
        'copy' => 'Salin entri',
        'copied' => 'Tersalin',
        'tag' => 'tag',
    ],

    'empty' => [
        'heading' => 'Tidak ada entri log yang cocok',
        'description' => 'Ubah filter, perbesar kedalaman pemindaian, atau pilih berkas log lain.',
        'description_no_files' => 'Belum ada berkas log di :path.',
    ],

];
