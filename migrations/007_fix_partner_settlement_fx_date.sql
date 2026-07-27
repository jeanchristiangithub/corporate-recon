-- fx_date_trn stores a calendar date in MoneyGram source and settlement files.
-- The existing DECIMAL column contains no populated values, so convert it to
-- the correct type before enabling End Month subset amendment.
ALTER TABLE partner_settlement_data
    MODIFY COLUMN fx_date_trn DATE NULL;
