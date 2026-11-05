<?php

return [
    'hermes_binary' => env('HERMES_BINARY', 'hermes'),

    'status_path' => env(
        'HOSTING_STATUS_PATH',
        env('HOME') . '/.hermes/state/hosting-monitor-status'
    ),

    'report_directory' => env(
        'HOSTING_REPORT_DIRECTORY',
        rtrim(env('HOME', ''), '/') . '/Documents/HostingVault/Laporan Harian'
    ),

    'websites' => [
        'Google' => [
            'url' => 'https' . '://' . 'google.com',
            'file' => 'google.status',
        ],
        'GitHub' => [
            'url' => 'https' . '://' . 'github.com',
            'file' => 'github.status',
        ],
    ],
];
