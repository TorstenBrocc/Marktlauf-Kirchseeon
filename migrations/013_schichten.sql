-- 013_schichten.sql
-- Einsatzplan/Schichten: Aufgaben mit Zeit/Ort + Zuteilung von Helfern (M:N)

-- Schicht = eine Aufgabe am Renntag (Titel + nähere Beschreibung) mit
-- konkretem Zeitfenster, Ort und Soll-Bedarf an Helfern.
CREATE TABLE IF NOT EXISTS `schichten` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `titel`       VARCHAR(255) NOT NULL,
    `beschreibung` TEXT NULL,
    `ort`         VARCHAR(255) NULL,
    `tag`         DATE NULL,
    `von`         TIME NULL,
    `bis`         TIME NULL,
    `bedarf`      INT UNSIGNED NOT NULL DEFAULT 1,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_schichten_tag` (`tag`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Zuteilung Helfer <-> Schicht (M:N). Ein Helfer kann mehreren Schichten
-- zugeteilt sein; eine Schicht hat mehrere Helfer.
CREATE TABLE IF NOT EXISTS `schicht_zuteilung` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `schicht_id` INT UNSIGNED NOT NULL,
    `helfer_id`  INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_zuteilung` (`schicht_id`, `helfer_id`),
    KEY `idx_zuteilung_helfer` (`helfer_id`),
    CONSTRAINT `fk_zuteilung_schicht` FOREIGN KEY (`schicht_id`)
        REFERENCES `schichten` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_zuteilung_helfer` FOREIGN KEY (`helfer_id`)
        REFERENCES `helfer` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
