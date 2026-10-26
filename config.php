<?php
declare(strict_types=1);

return [
    'app_name' => 'ClickShot Metrika',
    'timezone' => getenv('METRIKA_TIMEZONE') ?: 'Europe/Moscow',
    'db_path' => getenv('METRIKA_DB_PATH') ?: __DIR__ . '/storage/metrika.sqlite',
    'dashboard_password' => getenv('METRIKA_DASHBOARD_PASSWORD') ?: 'change-me-now',
    // Per-site aggregate only: no visitor identifiers or IP addresses.
    'consent_retention_days' => max(365, (int)(getenv('METRIKA_CONSENT_RETENTION_DAYS') ?: 365)),
    'hits_per_minute' => max(1, (int)(getenv('METRIKA_HITS_PER_MINUTE') ?: 6000)),
];
