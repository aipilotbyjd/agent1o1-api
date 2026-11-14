<?php

/*
|--------------------------------------------------------------------------
| Backups (Super Admin)
|--------------------------------------------------------------------------
|
| How the Super Admin backup tools behave. The backup engine itself is
| `config/backup.php` (spatie/laravel-backup).
|
*/

return [

    'enabled' => (bool) env('BACKUPS_ENABLED', false),

    /*
    | The filesystem disk backups are written to and downloaded from.
    */
    'disk' => env('BACKUP_DISK', 'backups'),

    /*
    | Scheduled runs (server time). `full` is a nightly dump; `hourly` is an
    | optional extra, off by default, to shorten the worst-case data loss.
    */
    'schedule' => [
        'nightly_at' => env('BACKUP_NIGHTLY_AT', '02:30'),
        'hourly' => (bool) env('BACKUP_HOURLY', false),
        'monitor_at' => env('BACKUP_MONITOR_AT', '03:30'),
    ],

    /*
    | How long a download link works. The link comes from the bucket itself
    | (a pre-signed URL), so it needs no server in the middle.
    */
    'download_url_ttl_minutes' => (int) env('BACKUP_DOWNLOAD_TTL_MINUTES', 5),

    /*
    | A run still `running` after this long is treated as dead (the worker
    | was killed) and marked failed by the next run's housekeeping.
    */
    'stale_after_minutes' => (int) env('BACKUP_STALE_AFTER_MINUTES', 120),

];
