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

-- ===================================================================
-- Description: Brevo API calls log table
-- ===================================================================

CREATE TABLE llx_brevo_log (
    rowid INT AUTO_INCREMENT PRIMARY KEY,
    entity INT NOT NULL DEFAULT 1,
    date_event DATETIME NOT NULL,
    method VARCHAR(8) NOT NULL,
    endpoint VARCHAR(255) NOT NULL,
    http_code INT NOT NULL DEFAULT 0,
    duration_ms INT NOT NULL DEFAULT 0,
    success TINYINT(1) NOT NULL DEFAULT 0,
    message TEXT NULL
) ENGINE=innodb;

CREATE INDEX idx_brevo_log_entity_date ON llx_brevo_log (entity, date_event);
CREATE INDEX idx_brevo_log_success ON llx_brevo_log (success);
