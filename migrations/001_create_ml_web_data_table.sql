-- Migration: Create consolidated ml_web_data table for all corporate partners
-- Database: filerecondb
-- Purpose: Consolidate all corporate partner web data uploads into a single table
--          with a partnerName column to track the source

CREATE TABLE IF NOT EXISTS `ml_web_data` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `partnerName` VARCHAR(255) NOT NULL COMMENT 'Corporate partner name (e.g., METROBANK HEAD OFFICE, PAYPAL CORPORATE)',
  `no` VARCHAR(100) NOT NULL COMMENT 'Reference number',
  `control_series_no` VARCHAR(100) COMMENT 'Control series number',
  `date_claimed` DATETIME COMMENT 'Date claimed',
  `kptn` VARCHAR(255) COMMENT 'KPTN code',
  `ccref_no` VARCHAR(100) NOT NULL COMMENT 'Clear credit reference number',
  `currency` VARCHAR(10) COMMENT 'Currency code (e.g., PHP, USD)',
  `amount` DECIMAL(12, 2) COMMENT 'Transaction amount',
  `ctc` VARCHAR(255) COMMENT 'CTC identifier',
  `ctp` VARCHAR(255) COMMENT 'CTP identifier',
  `sender_name` VARCHAR(255) COMMENT 'Sender/Remitter name',
  `sender_country` VARCHAR(100) COMMENT 'Sender country',
  `beneficiary_receiver` VARCHAR(255) COMMENT 'Beneficiary/Receiver name',
  `receiver_kyc` VARCHAR(255) COMMENT 'Receiver KYC verification',
  `receiver_phone` VARCHAR(20) COMMENT 'Receiver phone number',
  `operator` VARCHAR(100) COMMENT 'Operator code',
  `branch` VARCHAR(100) COMMENT 'Branch code',
  `remote_operator` VARCHAR(100) COMMENT 'Remote operator code',
  `remote_branch` VARCHAR(100) COMMENT 'Remote branch code',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  -- Indexes for efficient duplicate detection and querying
  UNIQUE KEY `uk_partner_ccref_date` (`partnerName`, `ccref_no`, `date_claimed`),
  KEY `idx_partner_name` (`partnerName`),
  KEY `idx_ccref_no` (`ccref_no`),
  KEY `idx_date_claimed` (`date_claimed`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Consolidated ML web data for all corporate partners';
