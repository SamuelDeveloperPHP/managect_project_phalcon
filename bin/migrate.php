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
    getenv('DB_PASSWORD') ?: 'phalcon123',
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS migrations (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        migration VARCHAR(255) NOT NULL UNIQUE,
        executed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$applied = $pdo
    ->query('SELECT migration FROM migrations ORDER BY migration')
    ->fetchAll(PDO::FETCH_COLUMN);

$migrationFiles = glob(dirname(__DIR__) . '/database/migrations/*.php');
sort($migrationFiles);

$executed = 0;

foreach ($migrationFiles as $migrationFile) {
    $migrationName = basename($migrationFile, '.php');

    if (in_array($migrationName, $applied, true)) {
        echo "[skip] {$migrationName}", PHP_EOL;
        continue;
    }

    $migration = require $migrationFile;

    if (!is_callable($migration)) {
        throw new RuntimeException(
            "A migration {$migrationName} deve retornar um callable."
        );
    }

    $migration($pdo);

    $statement = $pdo->prepare(
        'INSERT INTO migrations (migration) VALUES (:migration)'
    );
    $statement->execute(['migration' => $migrationName]);

    echo "[ok] {$migrationName}", PHP_EOL;
    $executed++;
}

echo $executed === 0
    ? 'Banco já está atualizado.' . PHP_EOL
    : "Migrations executadas: {$executed}" . PHP_EOL;
