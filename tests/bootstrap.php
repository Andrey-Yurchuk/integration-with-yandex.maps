<?php

declare(strict_types=1);

$testingEnvironment = [
    'APP_ENV' => 'testing',
    'APP_URL' => 'http://localhost',
    'APP_MAINTENANCE_DRIVER' => 'file',
    'BCRYPT_ROUNDS' => '4',
    'BROADCAST_CONNECTION' => 'null',
    'CACHE_STORE' => 'array',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => ':memory:',
    'DB_URL' => '',
    'MAIL_MAILER' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'LOG_CHANNEL' => 'stderr',
    'LOG_STACK' => 'stderr',
    'SANCTUM_STATEFUL_DOMAINS' => 'localhost,localhost:8080,127.0.0.1,127.0.0.1:8080',
    'SESSION_DRIVER' => 'database',
    'PULSE_ENABLED' => 'false',
    'TELESCOPE_ENABLED' => 'false',
    'NIGHTWATCH_ENABLED' => 'false',
];

foreach ($testingEnvironment as $name => $value) {
    putenv("{$name}={$value}");
    $_ENV[$name] = $value;
    $_SERVER[$name] = $value;
}

require dirname(__DIR__).'/vendor/autoload.php';
