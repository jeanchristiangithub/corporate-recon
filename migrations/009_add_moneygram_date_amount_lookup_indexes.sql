-- Supports End Month rows whose Excel Reference ID is blank and must be
-- resolved using Tran Date plus one of the fallback monetary values.
ALTER TABLE moneygram_partner_data
    ADD INDEX idx_moneygram_date_base (tran_date, base_tran_amt),
    ADD INDEX idx_moneygram_date_fx_share (tran_date, fx_rev_share_tran_amt),
    ADD INDEX idx_moneygram_date_comm (tran_date, comm_tran_amt);