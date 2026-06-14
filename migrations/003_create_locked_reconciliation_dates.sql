-- Migration: Create locked_reconciliation_dates table
-- Database: filerecondb
-- Purpose: Persist reconciliation-locked transaction dates used to block uploads

CREATE TABLE IF NOT EXISTS `locked_reconciliation_dates` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `corporate_partner` VARCHAR(100) NOT NULL,
    `transaction_date` DATE NOT NULL,
    `locked_by` VARCHAR(100) NULL,
    `locked_at` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_partner_txn_date` (`corporate_partner`, `transaction_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
