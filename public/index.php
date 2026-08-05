<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

$container = require dirname(__DIR__) . '/app/bootstrap.php';
$application = new Phalcon\Mvc\Application($container);

try {
    $application
        ->handle($_SERVER['REQUEST_URI'])
        ->send();
} catch (Throwable $exception) {
    error_log('[application] ' . preg_replace('/\s+/', ' ', trim($exception->getMessage())));

    http_response_code(500);
    echo 'Erro interno. Consulte os logs da aplicação.';
}
