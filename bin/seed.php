<?php

declare(strict_types=1);

$database = getenv('DB_DATABASE') ?: 'phalcon';
$dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
    getenv('DB_HOST') ?: 'mysql',
    (int) (getenv('DB_PORT') ?: 3306),
    $database
);

$pdo = new PDO(
    $dsn,
    getenv('DB_USERNAME') ?: 'phalcon',
    getenv('DB_PASSWORD') ?: '',
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);

$requestedSeeder = $argv[1] ?? null;
$seedersPath = dirname(__DIR__) . '/database/seeders';
$seederFiles = glob($seedersPath . '/*.php') ?: [];
sort($seederFiles);

if ($requestedSeeder !== null) {
    $requestedName = basename($requestedSeeder, '.php');
    $seederFiles = array_values(array_filter(
        $seederFiles,
        static fn (string $file): bool => basename($file, '.php') === $requestedName
    ));

    if ($seederFiles === []) {
        throw new RuntimeException("Seeder {$requestedName} não encontrada.");
    }
}

if ($seederFiles === []) {
    echo 'Nenhuma seeder encontrada.', PHP_EOL;
    exit(0);
}

foreach ($seederFiles as $seederFile) {
    $seederName = basename($seederFile, '.php');
    $seeder = require $seederFile;

    if (!is_callable($seeder)) {
        throw new RuntimeException("A seeder {$seederName} deve retornar um callable.");
    }

    try {
        $pdo->beginTransaction();
        $seeder($pdo);
        $pdo->commit();
        echo "[ok] {$seederName}", PHP_EOL;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
}

echo 'Seeders executadas: ' . count($seederFiles), PHP_EOL;
