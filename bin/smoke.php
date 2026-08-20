<?php

declare(strict_types=1);

$baseUrl = rtrim((string)(getenv('SMOKE_BASE_URL') ?: getenv('APP_URL') ?: 'http://localhost:8080'), '/');
$timeout = (float)(getenv('SMOKE_TIMEOUT') ?: 10);

$checks = [
    ['GET', '/healthz', [200], '"status":"ok"'],
    ['GET', '/readyz', [200], '"status":"ready"'],
    ['GET', '/', [200], 'Managect'],
    ['GET', '/login', [200], 'Entrar'],
    ['GET', '/termos', [200], 'Termos de Uso'],
    ['GET', '/privacidade', [200], 'Política de Privacidade'],
    ['GET', '/assets/app.css', [200], null],
    ['GET', '/assets/auth.css', [200], null],
    ['GET', '/assets/landing.css', [200], null],
    ['GET', '/dashboard', [302], null],
];

$failures = [];

foreach ($checks as [$method, $path, $expectedStatuses, $expectedBody]) {
    $url = $baseUrl . $path;
    $result = request($method, $url, $timeout);
    $status = $result['status'];
    $body = $result['body'];

    if (!in_array($status, $expectedStatuses, true)) {
        $failures[] = "{$method} {$path}: HTTP {$status}, esperado " . implode('/', $expectedStatuses);
        continue;
    }

    if ($expectedBody !== null && !str_contains($body, $expectedBody)) {
        $failures[] = "{$method} {$path}: corpo nao contem marcador esperado";
    }
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "[fail] {$failure}" . PHP_EOL);
    }

    exit(1);
}

echo "Smoke test OK em {$baseUrl}." . PHP_EOL;

function request(string $method, string $url, float $timeout): array
{
    $headers = [];
    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'timeout' => $timeout,
            'ignore_errors' => true,
            'follow_location' => 0,
            'header' => "User-Agent: ManagectSmoke/1.0\r\n",
        ],
    ]);

    set_error_handler(static function () {
        return true;
    });

    $body = file_get_contents($url, false, $context);
    $headers = $http_response_header ?? [];

    restore_error_handler();

    $status = 0;
    foreach ($headers as $header) {
        if (preg_match('/^HTTP\/\S+\s+(\d{3})\b/', $header, $matches)) {
            $status = (int)$matches[1];
            break;
        }
    }

    return [
        'status' => $status,
        'body' => is_string($body) ? $body : '',
    ];
}
