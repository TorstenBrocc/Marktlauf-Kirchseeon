-- User-Entwürfe für Briefvorlagen (sponsor + verein).
-- Ermöglicht paralleles Bearbeiten ohne gegenseitiges Überschreiben.
-- Ladelogik: eigener Draft → DB-Master → Code-Default.
CREATE TABLE briefvorlagen_entwurf (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id        INT UNSIGNED NOT NULL,
    vorlage_art    ENUM('sponsor','verein') NOT NULL,
    slug           VARCHAR(32) NOT NULL,
    betreff        VARCHAR(255) NULL,
    koerper_md     MEDIUMTEXT NULL,
    gespeichert_am DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                   ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_vorlage (user_id, vorlage_art, slug),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
