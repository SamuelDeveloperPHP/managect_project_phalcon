<?php

declare(strict_types=1);

// Histórico global de implantações. Uma versão de código é comum a todas as
// empresas; por isso esta tabela não pertence a um tenant específico.
return static function (PDO $pdo): void {
    $pdo->exec(
        'CREATE TABLE release_versions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            branch_name VARCHAR(190) NOT NULL,
            commit_sha CHAR(40) CHARACTER SET ascii NOT NULL,
            executed_by VARCHAR(190) NOT NULL,
            released_at DATETIME NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY release_versions_branch_commit_unique (branch_name, commit_sha),
            KEY release_versions_released_index (released_at, id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
          COLLATE=utf8mb4_unicode_ci'
    );
};
