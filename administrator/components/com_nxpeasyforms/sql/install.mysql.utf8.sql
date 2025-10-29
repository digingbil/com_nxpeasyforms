-- NXP Easy Forms component installation schema for MySQL
-- Ensures required tables exist with utf8mb4 collation.

CREATE TABLE IF NOT EXISTS `#__nxpeasyforms_forms` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(255) NOT NULL DEFAULT '',
    `alias` VARCHAR(255) DEFAULT NULL,
    `fields` LONGTEXT NOT NULL,
    `settings` LONGTEXT NOT NULL,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_active` (`active`),
    KEY `idx_created_at` (`created_at`),
    UNIQUE KEY `idx_alias` (`alias`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__nxpeasyforms_submissions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `form_id` BIGINT UNSIGNED NOT NULL,
    `submission_uuid` VARCHAR(36) NOT NULL,
    `data` LONGTEXT NOT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'new',
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` TEXT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_form_id` (`form_id`),
    KEY `idx_submission_uuid` (`submission_uuid`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
