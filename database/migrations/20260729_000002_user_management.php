<?php
declare(strict_types=1);
return static function (PDO $pdo): void {
    $pdo->exec("ALTER TABLE users
      ADD role ENUM('admin','user') NOT NULL DEFAULT 'user' AFTER password,
      ADD last_seen_at DATETIME NULL AFTER last_login_at,
      ADD deleted_at DATETIME NULL AFTER last_seen_at,
      ADD KEY users_online_index(last_seen_at),
      ADD KEY users_deleted_index(deleted_at)");
    $pdo->exec("UPDATE users SET role='admin' WHERE email='admin@phalcon.local'");
    $pdo->exec("CREATE TABLE audit_logs (
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      user_id BIGINT UNSIGNED NULL, action VARCHAR(80) NOT NULL,
      entity_type VARCHAR(80) NOT NULL, entity_id BIGINT UNSIGNED NULL,
      description VARCHAR(500) NOT NULL, ip_address VARCHAR(45) NULL,
      user_agent VARCHAR(255) NULL, metadata JSON NULL,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      KEY audit_user_index(user_id), KEY audit_created_index(created_at),
      KEY audit_entity_index(entity_type,entity_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
};
