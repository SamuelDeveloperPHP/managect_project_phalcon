<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $adminEmail = getenv('ADMIN_EMAIL') ?: 'admin@phalcon.local';
    $adminPassword = trim((string)getenv('ADMIN_PASSWORD'));

    if ($adminPassword === '') {
        throw new RuntimeException('Defina ADMIN_PASSWORD no arquivo .env antes de criar o usuario administrador.');
    }

    $pdo->exec(
        'CREATE TABLE users (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            email VARCHAR(190) NOT NULL,
            password VARCHAR(255) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            last_login_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY users_email_unique (email),
            KEY users_active_index (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
          COLLATE=utf8mb4_unicode_ci'
    );

    $statement = $pdo->prepare(
        'INSERT INTO users (name, email, password)
         VALUES (:name, :email, :password)'
    );
    $statement->execute([
        'name' => 'Administrador',
        'email' => $adminEmail,
        'password' => password_hash($adminPassword, PASSWORD_DEFAULT),
    ]);
};
