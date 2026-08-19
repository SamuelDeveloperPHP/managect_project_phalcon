<?php

declare(strict_types=1);

// Reforca unicidade de documentos e prepara o cadastro de CPF dos usuarios.
return static function (PDO $pdo): void {
    $database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    if ($database === '') {
        throw new RuntimeException('Nao foi possivel identificar o banco atual.');
    }

    $columnExists = static function (string $table, string $column) use ($pdo, $database): bool {
        $statement = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :table AND COLUMN_NAME = :column'
        );
        $statement->execute(['db' => $database, 'table' => $table, 'column' => $column]);

        return (int)$statement->fetchColumn() > 0;
    };

    $uniqueIndexExists = static function (string $table, string $column) use ($pdo, $database): bool {
        $statement = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = :db
               AND TABLE_NAME = :table
               AND COLUMN_NAME = :column
               AND NON_UNIQUE = 0'
        );
        $statement->execute(['db' => $database, 'table' => $table, 'column' => $column]);

        return (int)$statement->fetchColumn() > 0;
    };

    $indexExists = static function (string $table, string $index) use ($pdo, $database): bool {
        $statement = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :table AND INDEX_NAME = :idx'
        );
        $statement->execute(['db' => $database, 'table' => $table, 'idx' => $index]);

        return (int)$statement->fetchColumn() > 0;
    };

    if (!$columnExists('users', 'cpf')) {
        $pdo->exec('ALTER TABLE users ADD cpf VARCHAR(14) NULL AFTER email');
    }

    if (!$indexExists('users', 'users_cpf_unique')) {
        $duplicates = $pdo
            ->query("SELECT cpf FROM users WHERE cpf IS NOT NULL AND cpf <> '' GROUP BY cpf HAVING COUNT(*) > 1 LIMIT 1")
            ->fetchColumn();
        if ($duplicates !== false) {
            throw new RuntimeException('Existem usuarios com CPF duplicado; corrija antes de criar o indice unico.');
        }

        $pdo->exec('ALTER TABLE users ADD UNIQUE KEY users_cpf_unique (cpf)');
    }

    $companies = $pdo
        ->query('SELECT id, cnpj FROM companies ORDER BY id ASC')
        ->fetchAll(PDO::FETCH_ASSOC);
    $normalizedByCnpj = [];

    foreach ($companies as $company) {
        $id = (int)$company['id'];
        $current = (string)($company['cnpj'] ?? '');
        $digits = preg_replace('/\D/', '', $current) ?? '';

        if (strlen($digits) !== 14) {
            continue;
        }

        if (isset($normalizedByCnpj[$digits]) && $normalizedByCnpj[$digits] !== $id) {
            throw new RuntimeException('Existem empresas com CNPJ duplicado apos normalizacao.');
        }

        $normalizedByCnpj[$digits] = $id;

        if ($current !== $digits) {
            $statement = $pdo->prepare('UPDATE companies SET cnpj = :cnpj WHERE id = :id');
            $statement->execute(['cnpj' => $digits, 'id' => $id]);
        }
    }

    if (!$uniqueIndexExists('companies', 'cnpj')) {
        $duplicates = $pdo
            ->query('SELECT cnpj FROM companies GROUP BY cnpj HAVING COUNT(*) > 1 LIMIT 1')
            ->fetchColumn();
        if ($duplicates !== false) {
            throw new RuntimeException('Existem empresas com CNPJ duplicado; corrija antes de criar o indice unico.');
        }

        $pdo->exec('ALTER TABLE companies ADD UNIQUE KEY companies_cnpj_unique (cnpj)');
    }

    $adminDuplicates = $pdo
        ->query(
            "SELECT admin_recovery_email
             FROM companies
             WHERE deleted_at IS NULL
             GROUP BY admin_recovery_email
             HAVING COUNT(*) > 1
             LIMIT 1"
        )
        ->fetchColumn();

    if ($adminDuplicates !== false) {
        throw new RuntimeException('Existem empresas ativas com o mesmo e-mail de administrador.');
    }
};
