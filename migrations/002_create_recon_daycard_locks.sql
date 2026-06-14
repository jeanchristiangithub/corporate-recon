-- Migration: Create recon_daycard_locks table
-- Database: filerecondb
-- Purpose: Persist reconciliation day card lock state across reloads and users

CREATE TABLE IF NOT EXISTS `recon_daycard_locks` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `corporate_partner` VARCHAR(100) NOT NULL,
    `recon_date` DATE NOT NULL,
    `is_locked` TINYINT(1) NOT NULL DEFAULT 1,
    `locked_by` VARCHAR(100) NULL,
    `locked_at` DATETIME NULL,
    `unlocked_by` VARCHAR(100) NULL,
    `unlocked_at` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_partner_date` (`corporate_partner`, `recon_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
