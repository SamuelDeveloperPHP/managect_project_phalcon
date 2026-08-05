<?php

declare(strict_types=1);

return [
    'database' => [
        'host' => getenv('DB_HOST') ?: 'mysql',
        'port' => (int) (getenv('DB_PORT') ?: 3306),
        'username' => getenv('DB_USERNAME') ?: 'phalcon',
        'password' => getenv('DB_PASSWORD') ?: 'phalcon123',
        'dbname' => getenv('DB_DATABASE') ?: 'phalcon',
        'charset' => 'utf8mb4',
    ],
    'paths' => [
        'views' => dirname(__DIR__) . '/views/',
    ],
];
