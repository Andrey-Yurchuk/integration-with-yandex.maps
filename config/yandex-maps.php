<?php

return [
    'parser_mode' => env('YANDEX_MAPS_PARSER_MODE', 'hybrid'),

    'max_reviews' => (int) env('YANDEX_MAPS_MAX_REVIEWS', 600),

    'page_size' => (int) env('YANDEX_MAPS_PAGE_SIZE', 50),

    'timeout' => (int) env('YANDEX_MAPS_TIMEOUT', 120),

    'parser_version' => '1.0.0',

    'parser_url' => env('PARSER_URL'),
];
