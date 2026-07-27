-- Supports MoneyGram End Month matching without full-table scans.
ALTER TABLE moneygram_partner_data
    ADD INDEX idx_moneygram_reference_id (reference_id),
    ADD INDEX idx_moneygram_reference_tran_date (reference_id, tran_date);

ALTER TABLE partner_settlement_data
    ADD INDEX idx_settlement_partner_reference (partner_id, reference_id),
    ADD INDEX idx_settlement_reference_tran_date (reference_id, tran_date);
