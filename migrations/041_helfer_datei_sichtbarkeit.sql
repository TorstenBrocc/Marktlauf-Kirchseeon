-- 041_helfer_datei_sichtbarkeit.sql
-- Einteilungsspezifische Sichtbarkeit von Helfer-Dokumenten (Strang 3).
-- Ordnet ein Drive-Dokument (per drive_file_id) einer oder mehreren Schichten zu.
--
-- Semantik: Ein Dokument mit >= 1 Zuordnung ist NUR für Helfer sichtbar, die einer
-- der zugeordneten Schichten zugeteilt sind. Ein Dokument OHNE Zuordnung ist global
-- sichtbar (für alle bestätigten Helfer) — damit ist das Feature rückwärtskompatibel:
-- solange nichts zugeordnet wird, verhält sich die Helferseite wie bisher.
CREATE TABLE IF NOT EXISTS `helfer_datei_sichtbarkeit` (
    `drive_file_id` VARCHAR(128)  NOT NULL,
    `schicht_id`    INT UNSIGNED  NOT NULL,
    `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`drive_file_id`, `schicht_id`),
    KEY `idx_hds_schicht` (`schicht_id`),
    CONSTRAINT `fk_hds_schicht` FOREIGN KEY (`schicht_id`)
        REFERENCES `schichten` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
