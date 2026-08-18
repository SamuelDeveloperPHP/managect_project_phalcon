<?php

declare(strict_types=1);

// Alinha o schema ao código: 'master' é o super-admin do SaaS (nível acima de 'admin'),
// já verificado em ControllerBase/CompaniesController/User, mas ausente do enum. Idempotente.
return static function (PDO $pdo): void {
    $col = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch(PDO::FETCH_ASSOC);
    $type = is_array($col) ? strtolower((string) ($col['Type'] ?? '')) : '';

    if ($type !== '' && !str_contains($type, "'master'")) {
        $pdo->exec("ALTER TABLE users MODIFY role ENUM('master','admin','user') NOT NULL DEFAULT 'user'");
    }
};
