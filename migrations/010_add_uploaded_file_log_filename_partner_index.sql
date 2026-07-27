ALTER TABLE uploaded_file_logs
    ADD INDEX idx_uploaded_file_logs_filename_partner (filename, partner_id);
