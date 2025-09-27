-- ===================================================================
-- Upgrade script from 1.2.0 to 1.2.1
-- ===================================================================

ALTER TABLE llx_brevo_contactsync
    ADD COLUMN brevo_list_label VARCHAR(255) NOT NULL DEFAULT '' AFTER brevo_list_id;
