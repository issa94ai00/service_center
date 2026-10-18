<?php

return [

    /*
    | Database backups of every shop and of the central registry
    | (App\Backups\BackupService). Each backup is a gzipped mysqldump kept on
    | the local `backups` disk and, when a bucket is configured, copied to the
    | S3-compatible `backups_offsite` disk.
    */

    'disk' => 'backups',

    // Configure BACKUP_S3_* in .env to turn the offsite copy on.
    'offsite_disk' => env('BACKUP_S3_BUCKET') ? 'backups_offsite' : null,

    'schedule' => [
        'enabled' => (bool) env('BACKUP_SCHEDULE_ENABLED', true),
        'time' => env('BACKUP_SCHEDULE_TIME', '03:00'),
        'timezone' => env('BACKUP_SCHEDULE_TIMEZONE', 'Asia/Damascus'),
    ],

    /*
    | Scheduled backups are thinned grandfather-father-son style: the newest of
    | each of the last `daily` days, `weekly` weeks and `monthly` months is kept.
    | Backups taken by hand or before a restore/migration are kept for
    | `adhoc_days`, failed attempts for `failed_days`.
    */
    'retention' => [
        'daily' => (int) env('BACKUP_KEEP_DAILY', 7),
        'weekly' => (int) env('BACKUP_KEEP_WEEKLY', 4),
        'monthly' => (int) env('BACKUP_KEEP_MONTHLY', 3),
        'adhoc_days' => (int) env('BACKUP_KEEP_ADHOC_DAYS', 30),
        'failed_days' => 30,
    ],

    // A database whose newest good backup is older than this is flagged.
    'stale_after_hours' => 30,

    'binaries' => [
        'mysqldump' => env('BACKUP_MYSQLDUMP', 'mysqldump'),
        'mysql' => env('BACKUP_MYSQL', 'mysql'),
    ],

    // Seconds one dump or restore may run.
    'timeout' => 1800,
];
