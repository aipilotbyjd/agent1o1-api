<?php

use Spatie\Backup\Notifications\Notifiable;
use Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification;
use Spatie\Backup\Notifications\Notifications\BackupWasSuccessfulNotification;
use Spatie\Backup\Notifications\Notifications\CleanupHasFailedNotification;
use Spatie\Backup\Notifications\Notifications\CleanupWasSuccessfulNotification;
use Spatie\Backup\Notifications\Notifications\HealthyBackupWasFoundNotification;
use Spatie\Backup\Notifications\Notifications\UnhealthyBackupWasFoundNotification;
use Spatie\Backup\Tasks\Cleanup\Strategies\DefaultStrategy;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes;

/*
|--------------------------------------------------------------------------
| spatie/laravel-backup
|--------------------------------------------------------------------------
|
| Database backups only: user files live in the uploads bucket (versioned),
| and secrets (`APP_KEY`, Passport keys) are kept in the password manager,
| never inside a backup. See docs/BACKUP_RESTORE_RUNBOOK.md.
|
| Backups are immutable. Nothing in the app deletes one: `backup:clean` is
| never scheduled, and the `backups` disk's credentials have no delete
| permission. Expiry is the bucket's own lifecycle rule, after the Object
| Lock retention period ends.
|
*/

$disk = env('BACKUP_DISK', 'backups');

return [

    'backup' => [

        'name' => env('BACKUP_NAME', 'agent1o1'),

        'source' => [
            'files' => [
                'include' => [],
                'exclude' => [
                    base_path('vendor'),
                    base_path('node_modules'),
                    storage_path('framework'),
                ],
                'follow_links' => false,
                'ignore_unreadable_directories' => false,
                'relative_path' => null,
            ],

            'databases' => [
                env('BACKUP_DB_CONNECTION', env('DB_CONNECTION', 'pgsql')),
            ],
        ],

        'database_dump_compressor' => null,
        'database_dump_file_timestamp_format' => null,
        'database_dump_filename_base' => 'database',
        'database_dump_file_extension' => '',

        'destination' => [
            'compression_method' => ZipArchive::CM_DEFAULT,
            'compression_level' => 9,
            'filename_prefix' => '',
            'disks' => [$disk],
            'continue_on_failure' => false,
        ],

        'temporary_directory' => storage_path('app/backup-temp'),

        'password' => env('BACKUP_ARCHIVE_PASSWORD'),
        'encryption' => 'default',

        'verify_backup' => false,
        'tries' => 1,
        'retry_delay' => 0,
    ],

    'notifications' => [
        'notifications' => [
            BackupHasFailedNotification::class => ['mail'],
            UnhealthyBackupWasFoundNotification::class => ['mail'],
            CleanupHasFailedNotification::class => ['mail'],
            BackupWasSuccessfulNotification::class => [],
            HealthyBackupWasFoundNotification::class => [],
            CleanupWasSuccessfulNotification::class => [],
        ],

        'notifiable' => Notifiable::class,

        'mail' => [
            'to' => env('BACKUP_NOTIFICATION_EMAIL', env('MAIL_FROM_ADDRESS', 'hello@example.com')),
            'from' => [
                'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
                'name' => env('MAIL_FROM_NAME', 'Example'),
            ],
        ],

        'slack' => [
            'webhook_url' => '',
            'channel' => null,
            'username' => null,
            'icon' => null,
        ],

        'discord' => [
            'webhook_url' => '',
            'username' => '',
            'avatar_url' => '',
        ],

        'webhook' => [
            'url' => '',
        ],
    ],

    'log_channel' => null,

    'monitor_backups' => [
        [
            'name' => env('BACKUP_NAME', 'agent1o1'),
            'disks' => [$disk],
            'health_checks' => [
                MaximumAgeInDays::class => 1,
                MaximumStorageInMegabytes::class => (int) env('BACKUP_MAX_STORAGE_MB', 50000),
            ],
        ],
    ],

    /*
    | Present only because the package requires the key. `backup:clean` is
    | deliberately never scheduled and the backups disk cannot delete.
    */
    'cleanup' => [
        'strategy' => DefaultStrategy::class,
        'default_strategy' => [
            'keep_all_backups_for_days' => 36500,
            'keep_daily_backups_for_days' => 36500,
            'keep_weekly_backups_for_weeks' => 5200,
            'keep_monthly_backups_for_months' => 1200,
            'keep_yearly_backups_for_years' => 100,
            'delete_oldest_backups_when_using_more_megabytes_than' => PHP_INT_MAX,
        ],
        'tries' => 1,
        'retry_delay' => 0,
    ],

];
