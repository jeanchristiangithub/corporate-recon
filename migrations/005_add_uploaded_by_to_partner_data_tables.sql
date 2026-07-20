ALTER TABLE mbtc_partner_data
    ADD COLUMN uploaded_by VARCHAR(255) NULL;

ALTER TABLE wic_partner_data
    ADD COLUMN uploaded_by VARCHAR(255) NULL;

ALTER TABLE rcbc_partner_data
    ADD COLUMN uploaded_by VARCHAR(255) NULL;

ALTER TABLE skybridgepaymentinc_partner_data
    ADD COLUMN uploaded_by VARCHAR(255) NULL;
