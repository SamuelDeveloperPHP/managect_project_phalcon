<?php

declare(strict_types=1);

// Registro do aceite dos Termos de Uso por usuário (trilha de auditoria):
// data do aceite, versão aceita e IP de origem. Idempotente.
return static function (PDO $pdo): void {
    $userColumns = $pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('terms_accepted_at', $userColumns, true)) {
        $pdo->exec(
            "ALTER TABLE users
                ADD terms_accepted_at DATETIME NULL AFTER permissions,
                ADD terms_version VARCHAR(20) NULL AFTER terms_accepted_at,
                ADD terms_accepted_ip VARCHAR(45) NULL AFTER terms_version"
        );
    }
};
