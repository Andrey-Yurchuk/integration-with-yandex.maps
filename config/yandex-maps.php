<?php

return [
    'parser_mode' => env('YANDEX_MAPS_PARSER_MODE', 'internal'),

    'max_reviews' => (int) env('YANDEX_MAPS_MAX_REVIEWS', 600),

    'page_size' => (int) env('YANDEX_MAPS_PAGE_SIZE', 50),

    'timeout' => (int) env('YANDEX_MAPS_TIMEOUT', 300),

    'parser_version' => 'internal-1.0.0',

    'parser_url' => env('PARSER_URL'),

    'user_agent' => env(
        'YANDEX_MAPS_USER_AGENT',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
    ),

    'accept_language' => env('YANDEX_MAPS_ACCEPT_LANGUAGE', 'ru-RU,ru;q=0.9'),

    'retry' => [
        'times' => (int) env('YANDEX_MAPS_RETRY_TIMES', 2),
        'sleep_ms' => (int) env('YANDEX_MAPS_RETRY_SLEEP_MS', 500),
        'page_times' => (int) env('YANDEX_MAPS_PAGE_RETRY_TIMES', 2),
        'page_sleep_ms' => (int) env('YANDEX_MAPS_PAGE_RETRY_SLEEP_MS', 500),
    ],
];
