<?php

declare(strict_types=1);

// Complementa o registro de implantação com notas de release estruturadas.
// As colunas são anuláveis para preservar o histórico já registrado.
return static function (PDO $pdo): void {
    $database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    $columns = $pdo->prepare(
        'SELECT COLUMN_NAME FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = :database_name AND TABLE_NAME = :table_name'
    );
    $columns->execute(['database_name' => $database, 'table_name' => 'release_versions']);
    $existing = array_flip($columns->fetchAll(PDO::FETCH_COLUMN));

    $additions = [
        'commit_message' => 'ALTER TABLE release_versions ADD commit_message VARCHAR(500) NULL AFTER commit_sha',
        'implemented_notes' => 'ALTER TABLE release_versions ADD implemented_notes JSON NULL AFTER commit_message',
        'fixed_notes' => 'ALTER TABLE release_versions ADD fixed_notes JSON NULL AFTER implemented_notes',
        'updated_notes' => 'ALTER TABLE release_versions ADD updated_notes JSON NULL AFTER fixed_notes',
    ];

    foreach ($additions as $column => $sql) {
        if (!isset($existing[$column])) {
            $pdo->exec($sql);
        }
    }
};
