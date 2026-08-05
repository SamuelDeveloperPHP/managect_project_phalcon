<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $pdo->exec(
        'CREATE TABLE projects (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(190) NOT NULL,
            code VARCHAR(80) NULL,
            client VARCHAR(190) NULL,
            description TEXT NULL,
            status VARCHAR(40) NOT NULL DEFAULT "in_progress",
            priority VARCHAR(40) NOT NULL DEFAULT "medium",
            leader_id BIGINT UNSIGNED NULL,
            start_date DATE NULL,
            deadline DATE NULL,
            budget DECIMAL(14,2) NULL,
            image_path VARCHAR(255) NULL,
            created_by BIGINT UNSIGNED NULL,
            updated_by BIGINT UNSIGNED NULL,
            deleted_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,
            KEY projects_status_index (status),
            KEY projects_leader_index (leader_id),
            KEY projects_deadline_index (deadline),
            KEY projects_deleted_index (deleted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
          COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE project_members (
            project_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (project_id, user_id),
            KEY project_members_user_index (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
          COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE project_files (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            project_id BIGINT UNSIGNED NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            stored_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            mime_type VARCHAR(120) NULL,
            file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
            uploaded_by BIGINT UNSIGNED NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY project_files_project_index (project_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
          COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'ALTER TABLE gantt_tasks
            ADD project_id BIGINT UNSIGNED NULL AFTER id,
            ADD KEY gantt_tasks_project_sort_index (project_id, sort_order)'
    );
};
