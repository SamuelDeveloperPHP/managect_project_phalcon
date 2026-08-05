<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $pdo->exec(
        'CREATE TABLE gantt_tasks (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(80) NULL,
            name VARCHAR(190) NOT NULL,
            description TEXT NULL,
            level INT UNSIGNED NOT NULL DEFAULT 0,
            status VARCHAR(40) NOT NULL DEFAULT "STATUS_ACTIVE",
            progress TINYINT UNSIGNED NOT NULL DEFAULT 0,
            start_at DATETIME NOT NULL,
            end_at DATETIME NOT NULL,
            duration INT UNSIGNED NOT NULL DEFAULT 1,
            depends VARCHAR(255) NOT NULL DEFAULT "",
            sort_order INT UNSIGNED NOT NULL DEFAULT 0,
            collapsed TINYINT(1) NOT NULL DEFAULT 0,
            start_is_milestone TINYINT(1) NOT NULL DEFAULT 0,
            end_is_milestone TINYINT(1) NOT NULL DEFAULT 0,
            created_by BIGINT UNSIGNED NULL,
            updated_by BIGINT UNSIGNED NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,
            KEY gantt_tasks_sort_index (sort_order),
            KEY gantt_tasks_dates_index (start_at, end_at),
            KEY gantt_tasks_status_index (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
          COLLATE=utf8mb4_unicode_ci'
    );
};
