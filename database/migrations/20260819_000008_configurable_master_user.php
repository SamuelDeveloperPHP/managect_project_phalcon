<?php

declare(strict_types=1);

// Garante um usuario master configuravel por ambiente. Em bancos novos corrige
// o caso em que ADMIN_EMAIL customizado era criado como usuario comum.
return static function (PDO $pdo): void {
    $explicitMasterEmail = strtolower(trim((string) getenv('MASTER_EMAIL')));
    $masterEmail = $explicitMasterEmail !== ''
        ? $explicitMasterEmail
        : strtolower(trim((string) (getenv('ADMIN_EMAIL') ?: 'admin@phalcon.local')));

    if (!filter_var($masterEmail, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('MASTER_EMAIL/ADMIN_EMAIL deve conter um e-mail valido.');
    }

    $masterName = trim((string) (getenv('MASTER_NAME') ?: 'Administrador Master'));
    if ($masterName === '') {
        $masterName = 'Administrador Master';
    }

    $masterPassword = trim((string) getenv('MASTER_PASSWORD'));
    if ($masterPassword === '' && $explicitMasterEmail === '') {
        $masterPassword = trim((string) getenv('ADMIN_PASSWORD'));
    }

    $userColumns = array_flip($pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN));
    $companyColumns = array_flip($pdo->query('SHOW COLUMNS FROM companies')->fetchAll(PDO::FETCH_COLUMN));

    if (!isset($userColumns['role'])) {
        throw new RuntimeException('Coluna users.role nao encontrada para configurar usuario master.');
    }

    $roleColumn = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch(PDO::FETCH_ASSOC);
    $roleType = is_array($roleColumn) ? strtolower((string) ($roleColumn['Type'] ?? '')) : '';
    if ($roleType !== '' && !str_contains($roleType, "'master'")) {
        $pdo->exec("ALTER TABLE users MODIFY role ENUM('master','admin','user') NOT NULL DEFAULT 'user'");
    }

    $defaultCompanyId = null;
    if ($companyColumns !== []) {
        $companyId = $pdo->query('SELECT id FROM companies ORDER BY id ASC LIMIT 1')->fetchColumn();
        $defaultCompanyId = $companyId !== false ? (int) $companyId : null;
    }

    $statement = $pdo->prepare('SELECT id FROM users WHERE email = :email ORDER BY id ASC LIMIT 1');
    $statement->execute(['email' => $masterEmail]);
    $existingId = $statement->fetchColumn();

    $permissions = json_encode([
        'can_manage_projects' => true,
        'can_view_reports' => true,
        'can_manage_users' => true,
        'can_manage_company' => true,
    ], JSON_UNESCAPED_UNICODE);

    if ($existingId !== false) {
        $updates = [
            'name = :name',
            'role = :role',
        ];
        $params = [
            'id' => (int) $existingId,
            'name' => $masterName,
            'role' => 'master',
        ];

        if (isset($userColumns['company_id']) && $defaultCompanyId !== null) {
            $updates[] = 'company_id = COALESCE(company_id, :company_id)';
            $params['company_id'] = $defaultCompanyId;
        }

        if (isset($userColumns['permissions'])) {
            $updates[] = 'permissions = :permissions';
            $params['permissions'] = $permissions;
        }

        if (isset($userColumns['is_active'])) {
            $updates[] = 'is_active = 1';
        }

        if (isset($userColumns['deleted_at'])) {
            $updates[] = 'deleted_at = NULL';
        }

        if ($masterPassword !== '') {
            $updates[] = 'password = :password';
            $params['password'] = password_hash($masterPassword, PASSWORD_DEFAULT);
        }

        $update = $pdo->prepare('UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = :id');
        $update->execute($params);

        return;
    }

    if ($masterPassword === '') {
        throw new RuntimeException('Defina MASTER_PASSWORD para criar o usuario master configurado.');
    }

    if (isset($userColumns['company_id']) && $defaultCompanyId === null) {
        throw new RuntimeException('Nao foi encontrada empresa padrao para vincular o usuario master.');
    }

    $data = [
        'name' => $masterName,
        'email' => $masterEmail,
        'password' => password_hash($masterPassword, PASSWORD_DEFAULT),
        'role' => 'master',
    ];

    if (isset($userColumns['company_id'])) {
        $data['company_id'] = $defaultCompanyId;
    }

    if (isset($userColumns['permissions'])) {
        $data['permissions'] = $permissions;
    }

    if (isset($userColumns['is_active'])) {
        $data['is_active'] = 1;
    }

    $columns = array_keys($data);
    $placeholders = array_map(static fn (string $column): string => ':' . $column, $columns);

    $insert = $pdo->prepare(sprintf(
        'INSERT INTO users (%s) VALUES (%s)',
        implode(', ', $columns),
        implode(', ', $placeholders)
    ));
    $insert->execute($data);
};
