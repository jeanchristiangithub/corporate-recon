ALTER TABLE moneygram_partner_data
    ADD INDEX idx_moneygram_transaction_tran_date (transaction_id, tran_date);
