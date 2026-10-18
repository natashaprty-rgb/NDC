<?php

return [
    'title' => 'Backups',
    'subtitle' => 'Status register of database and file backups and restoration tests.',
    'last_success' => 'Last successful backup',
    'last_restore_test' => 'Last restoration test',
    'restore_never_help' => 'No restoration test recorded yet',
    'command' => 'Automatic backup command',
    'command_help' => 'Runs daily at 01:30 through the scheduler (php artisan ndc:backup --files).',
    'history' => 'Backup history',
    'none' => 'No backups have been recorded yet.',
    'completed' => 'Completed',
    'file' => 'File name',
    'restore_test' => 'Restoration test',
    'record_restore' => 'Record test',
    'result' => 'Result',
    'record_manual' => 'Record a backup made outside the system',
    'manual_help' => 'Use this for backups taken by the hosting team or copied off-site. Do not enter passwords or access keys.',
    'location' => 'Storage location',
    'location_help' => 'For example: "Off-site encrypted disk #2" or "Backup server /backups/ndc".',
    'recorded' => 'Backup recorded.',
    'restore_recorded' => 'Restoration test recorded.',
];
