-- ===================================================================
-- Copyright (C) 2024 Meditrust
-- Description: Brevo contact synchronisation table
-- ===================================================================

CREATE TABLE llx_brevo_contactsync (
    rowid INT AUTO_INCREMENT PRIMARY KEY,
    entity INT NOT NULL DEFAULT 1,
    fk_socpeople INT NOT NULL DEFAULT 0,
    fk_societe INT NOT NULL DEFAULT 0,
    brevo_list_id INT NOT NULL,
    brevo_contact_id VARCHAR(128) NOT NULL,
    date_sync DATETIME NOT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'ok',
    CONSTRAINT idx_brevo_contactsync_contact_list UNIQUE (entity, fk_socpeople, fk_societe, brevo_list_id)
) ENGINE=innodb;

CREATE INDEX idx_brevo_contactsync_socpeople ON llx_brevo_contactsync (fk_socpeople);
CREATE INDEX idx_brevo_contactsync_societe ON llx_brevo_contactsync (fk_societe);
CREATE INDEX idx_brevo_contactsync_status ON llx_brevo_contactsync (status);
