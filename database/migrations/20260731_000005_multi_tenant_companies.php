<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    // 1. Create companies table
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS companies (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(190) NOT NULL,
            cnpj VARCHAR(20) NOT NULL UNIQUE,
            domain VARCHAR(190) NOT NULL UNIQUE,
            zip_code VARCHAR(20) NULL,
            street VARCHAR(190) NULL,
            number VARCHAR(50) NULL,
            complement VARCHAR(100) NULL,
            neighborhood VARCHAR(100) NULL,
            city VARCHAR(100) NULL,
            state VARCHAR(10) NULL,
            logo_path VARCHAR(255) NULL,
            contact_name VARCHAR(120) NOT NULL,
            contact_email VARCHAR(190) NOT NULL,
            contact_whatsapp VARCHAR(40) NOT NULL,
            admin_recovery_email VARCHAR(190) NOT NULL,
            secondary_recovery_email VARCHAR(190) NOT NULL,
            deleted_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,
            KEY companies_cnpj_index (cnpj),
            KEY companies_domain_index (domain)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
          COLLATE=utf8mb4_unicode_ci'
    );

    // 2. Insert default company if none exists
    $companyCount = (int) $pdo->query('SELECT COUNT(*) FROM companies')->fetchColumn();
    if ($companyCount === 0) {
        $stmt = $pdo->prepare(
            'INSERT INTO companies (
                name, cnpj, domain, contact_name, contact_email, contact_whatsapp,
                admin_recovery_email, secondary_recovery_email
            ) VALUES (
                :name, :cnpj, :domain, :contact_name, :contact_email, :contact_whatsapp,
                :admin_recovery_email, :secondary_recovery_email
            )'
        );
        $stmt->execute([
            'name' => 'Empresa Principal',
            'cnpj' => '00.000.000/0001-91',
            'domain' => 'phalcon.local',
            'contact_name' => 'Suporte Técnico',
            'contact_email' => 'admin@phalcon.local',
            'contact_whatsapp' => '(11) 99999-9999',
            'admin_recovery_email' => 'recuperacao@phalcon.local',
            'secondary_recovery_email' => 'backup@phalcon.local',
        ]);
    }

    $defaultCompanyId = (int) $pdo->query('SELECT id FROM companies ORDER BY id ASC LIMIT 1')->fetchColumn();

    // 3. Add company_id and permissions to users
    $userColumns = $pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('company_id', $userColumns, true)) {
        $pdo->exec(
            "ALTER TABLE users
                ADD company_id BIGINT UNSIGNED NULL AFTER id,
                ADD permissions TEXT NULL AFTER role,
                ADD KEY users_company_index (company_id)"
        );
        $pdo->exec("UPDATE users SET company_id = {$defaultCompanyId} WHERE company_id IS NULL");
    }

    // 4. Add company_id to projects
    $projectColumns = $pdo->query('SHOW COLUMNS FROM projects')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('company_id', $projectColumns, true)) {
        $pdo->exec(
            "ALTER TABLE projects
                ADD company_id BIGINT UNSIGNED NULL AFTER id,
                ADD KEY projects_company_index (company_id)"
        );
        $pdo->exec("UPDATE projects SET company_id = {$defaultCompanyId} WHERE company_id IS NULL");
    }

    // 5. Add company_id to gantt_tasks
    $ganttColumns = $pdo->query('SHOW COLUMNS FROM gantt_tasks')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('company_id', $ganttColumns, true)) {
        $pdo->exec(
            "ALTER TABLE gantt_tasks
                ADD company_id BIGINT UNSIGNED NULL AFTER project_id,
                ADD KEY gantt_tasks_company_index (company_id)"
        );
        $pdo->exec("UPDATE gantt_tasks SET company_id = {$defaultCompanyId} WHERE company_id IS NULL");
    }

    // 6. Add company_id to project_files
    $fileColumns = $pdo->query('SHOW COLUMNS FROM project_files')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('company_id', $fileColumns, true)) {
        $pdo->exec(
            "ALTER TABLE project_files
                ADD company_id BIGINT UNSIGNED NULL AFTER project_id,
                ADD KEY project_files_company_index (company_id)"
        );
        $pdo->exec("UPDATE project_files SET company_id = {$defaultCompanyId} WHERE company_id IS NULL");
    }

    // 7. Add company_id to audit_logs
    $auditColumns = $pdo->query('SHOW COLUMNS FROM audit_logs')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('company_id', $auditColumns, true)) {
        $pdo->exec(
            "ALTER TABLE audit_logs
                ADD company_id BIGINT UNSIGNED NULL AFTER user_id,
                ADD KEY audit_logs_company_index (company_id)"
        );
        $pdo->exec("UPDATE audit_logs SET company_id = {$defaultCompanyId} WHERE company_id IS NULL");
    }
};
