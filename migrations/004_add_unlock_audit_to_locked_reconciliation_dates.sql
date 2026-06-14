-- Migration: Add unlock audit fields to locked_reconciliation_dates table
-- Database: filerecondb
-- Purpose: Track unlock events and preserve complete audit history

ALTER TABLE `locked_reconciliation_dates`
ADD COLUMN `unlocked_by` VARCHAR(100) NULL AFTER `locked_at`,
ADD COLUMN `unlocked_at` DATETIME NULL AFTER `unlocked_by`;

-- Create index for faster queries on unlock status
ALTER TABLE `locked_reconciliation_dates`
ADD INDEX `idx_partner_unlock_status` (`corporate_partner`, `unlocked_at`);
