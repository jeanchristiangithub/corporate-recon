-- partner_id was previously GENERATED ALWAYS AS (NULL), which prevented the
-- settlement uploader from persisting the selected partner ID.
ALTER TABLE partner_settlement_data
    DROP COLUMN partner_id,
    ADD COLUMN partner_id VARCHAR(45) NULL AFTER id;
