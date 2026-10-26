<?php

return [
    'hsts' => env('OPS_HSTS_ENABLED', false),
    'trusted_proxies' => array_values(array_filter(array_map('trim', explode(',', (string) env('OPS_TRUSTED_PROXIES', ''))))),
    'backup_directory' => env('OPS_BACKUP_DIR', storage_path('app/backups')),
    'backup_key_file' => env('OPS_BACKUP_KEY_FILE', ''),
    'mysqldump' => env('OPS_MYSQLDUMP_BINARY', 'mysqldump'),
    'mysql' => env('OPS_MYSQL_BINARY', 'mysql'),
    'restore_username' => env('OPS_RESTORE_DB_USERNAME', ''),
    'restore_password' => env('OPS_RESTORE_DB_PASSWORD', ''),
    'process_timeout' => 600,
];
