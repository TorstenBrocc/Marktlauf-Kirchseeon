-- ATSV Kirchseeon Marktlauf Dashboard
-- Phase 1 Schema: users, helfer, helfer_slots, helfer_beitrag, login_attempts, register_attempts
-- MySQL 5.7+ / MariaDB 10.2+

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Users (Admin + Orga)
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `pass_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('admin', 'orga') NOT NULL DEFAULT 'orga',
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Helfer
CREATE TABLE IF NOT EXISTS `helfer` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `uuid` CHAR(36) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(30) NOT NULL,
    `status` ENUM('neu', 'bestaetigt') NOT NULL DEFAULT 'neu',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_helfer_uuid` (`uuid`),
    UNIQUE KEY `uk_helfer_email` (`email`),
    KEY `idx_helfer_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Helfer Slots (Verfügbarkeit)
CREATE TABLE IF NOT EXISTS `helfer_slots` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `helfer_id` INT UNSIGNED NOT NULL,
    `tag` DATE NOT NULL,
    `zeitfenster` ENUM('vormittag', 'nachmittag') NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_slots_helfer` (`helfer_id`),
    KEY `idx_slots_tag` (`tag`),
    CONSTRAINT `fk_slots_helfer` FOREIGN KEY (`helfer_id`)
        REFERENCES `helfer` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Helfer Beitrag (Was kann mitgebracht werden)
CREATE TABLE IF NOT EXISTS `helfer_beitrag` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `helfer_id` INT UNSIGNED NOT NULL,
    `typ` ENUM('kuchen', 'getraenke', 'equipment', 'sonstiges') NOT NULL,
    `freitext` TEXT,
    PRIMARY KEY (`id`),
    KEY `idx_beitrag_helfer` (`helfer_id`),
    CONSTRAINT `fk_beitrag_helfer` FOREIGN KEY (`helfer_id`)
        REFERENCES `helfer` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Login Rate Limiting
CREATE TABLE IF NOT EXISTS `login_attempts` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ip` VARCHAR(45) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `ts` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_login_ip_email_ts` (`ip`, `email`, `ts`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Register Rate Limiting (öffentliche Formulare)
CREATE TABLE IF NOT EXISTS `register_attempts` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ip` VARCHAR(45) NOT NULL,
    `ts` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_register_ip_ts` (`ip`, `ts`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
