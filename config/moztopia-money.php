<?php

declare(strict_types=1);

use Moztopia\Money\FrankfurterProvider;

return [
    'provider' => FrankfurterProvider::class,
    'http' => [
        'connect_timeout_ms' => 2000,
        'response_timeout_ms' => 5000,
        'max_response_bytes' => 2097152,
    ],
    'frankfurter' => [
        'base_url' => 'https://api.frankfurter.dev/v2',
        'providers' => [],
        'max_attempts' => 3,
        'retry_delay_ms' => 100,
    ],
    'cache' => [
        'enabled' => true,
        'store' => null,
        'ttl_seconds' => 3600,
        'historical_ttl_seconds' => 86400,
        'allow_stale_on_error' => false,
        'max_stale_seconds' => 0,
        'lock_seconds' => 60,
        'lock_wait_seconds' => 5,
    ],
    'custom_currencies' => [],
    'currency_fields' => [],
];
