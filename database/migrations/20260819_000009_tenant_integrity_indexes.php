<?php

declare(strict_types=1);

// Reforca o isolamento multi-tenant na camada fisica sem apagar dados.
return static function (PDO $pdo): void {
    $database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    if ($database === '') {
        throw new RuntimeException('Nao foi possivel identificar o banco atual.');
    }

    $tableExists = static function (string $table) use ($pdo, $database): bool {
        $statement = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :table'
        );
        $statement->execute(['db' => $database, 'table' => $table]);

        return (int)$statement->fetchColumn() > 0;
    };

    $columnExists = static function (string $table, string $column) use ($pdo, $database): bool {
        $statement = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :table AND COLUMN_NAME = :column'
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

    $constraintExists = static function (string $table, string $constraint) use ($pdo, $database): bool {
        $statement = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = :db AND TABLE_NAME = :table AND CONSTRAINT_NAME = :constraint'
        );
        $statement->execute(['db' => $database, 'table' => $table, 'constraint' => $constraint]);

        return (int)$statement->fetchColumn() > 0;
    };

    $defaultCompanyId = $pdo->query('SELECT id FROM companies ORDER BY id ASC LIMIT 1')->fetchColumn();
    if ($defaultCompanyId === false) {
        throw new RuntimeException('Nao foi encontrada empresa padrao para reforcar tenant.');
    }
    $defaultCompanyId = (int)$defaultCompanyId;

    foreach (['users', 'projects', 'gantt_tasks', 'project_files', 'audit_logs'] as $table) {
        if (!$tableExists($table) || !$columnExists($table, 'company_id')) {
            continue;
        }

        $pdo
            ->prepare(
                "UPDATE {$table} t
                 LEFT JOIN companies c ON c.id = t.company_id
                 SET t.company_id = :company_id
                 WHERE t.company_id IS NULL OR c.id IS NULL"
            )
            ->execute(['company_id' => $defaultCompanyId]);

        $pdo->exec("ALTER TABLE {$table} MODIFY company_id BIGINT UNSIGNED NOT NULL");
    }

    $indexes = [
        ['users', 'users_tenant_active_index', 'ALTER TABLE users ADD KEY users_tenant_active_index (company_id, deleted_at, is_active)'],
        ['projects', 'projects_tenant_deleted_index', 'ALTER TABLE projects ADD KEY projects_tenant_deleted_index (company_id, deleted_at, id)'],
        ['gantt_tasks', 'gantt_tasks_tenant_project_sort_index', 'ALTER TABLE gantt_tasks ADD KEY gantt_tasks_tenant_project_sort_index (company_id, project_id, sort_order)'],
        ['project_files', 'project_files_tenant_project_index', 'ALTER TABLE project_files ADD KEY project_files_tenant_project_index (company_id, project_id)'],
        ['audit_logs', 'audit_logs_tenant_created_index', 'ALTER TABLE audit_logs ADD KEY audit_logs_tenant_created_index (company_id, created_at)'],
    ];

    foreach ($indexes as [$table, $index, $sql]) {
        if ($tableExists($table) && !$indexExists($table, $index)) {
            $pdo->exec($sql);
        }
    }

    $foreignKeys = [
        ['users', 'users_company_fk', 'ALTER TABLE users ADD CONSTRAINT users_company_fk FOREIGN KEY (company_id) REFERENCES companies(id)'],
        ['projects', 'projects_company_fk', 'ALTER TABLE projects ADD CONSTRAINT projects_company_fk FOREIGN KEY (company_id) REFERENCES companies(id)'],
        ['gantt_tasks', 'gantt_tasks_company_fk', 'ALTER TABLE gantt_tasks ADD CONSTRAINT gantt_tasks_company_fk FOREIGN KEY (company_id) REFERENCES companies(id)'],
        ['project_files', 'project_files_company_fk', 'ALTER TABLE project_files ADD CONSTRAINT project_files_company_fk FOREIGN KEY (company_id) REFERENCES companies(id)'],
        ['audit_logs', 'audit_logs_company_fk', 'ALTER TABLE audit_logs ADD CONSTRAINT audit_logs_company_fk FOREIGN KEY (company_id) REFERENCES companies(id)'],
    ];

    foreach ($foreignKeys as [$table, $constraint, $sql]) {
        if ($tableExists($table) && !$constraintExists($table, $constraint)) {
            $pdo->exec($sql);
        }
    }
};
