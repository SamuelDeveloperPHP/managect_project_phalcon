<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    $column = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = :database_name
           AND TABLE_NAME = :table_name
           AND COLUMN_NAME = :column_name'
    );
    $column->execute([
        'database_name' => $database,
        'table_name' => 'release_versions',
        'column_name' => 'commit_author',
    ]);

    if ((int) $column->fetchColumn() === 0) {
        $pdo->exec('ALTER TABLE release_versions ADD commit_author VARCHAR(190) NULL AFTER commit_message');
    }
};
