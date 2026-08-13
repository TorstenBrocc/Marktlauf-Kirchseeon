-- 007_sponsor_ansprechpartner.sql
-- Mehrere Ansprechpartner pro Sponsor + erweiterte Kein-Kontakt-Felder

-- Neue Tabelle für mehrere Ansprechpartner pro Sponsor
CREATE TABLE sponsor_ansprechpartner (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sponsor_id INT UNSIGNED NOT NULL,
  anrede     ENUM('Herr','Frau','Divers','') NOT NULL DEFAULT '',
  vorname    VARCHAR(128) NOT NULL DEFAULT '',
  nachname   VARCHAR(128) NOT NULL DEFAULT '',
  funktion   VARCHAR(128) NOT NULL DEFAULT '',
  email      VARCHAR(255) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ap_sponsor FOREIGN KEY (sponsor_id) REFERENCES sponsors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kein-Kontakt erweitern
ALTER TABLE sponsors
  ADD COLUMN kein_kontakt_grund    TEXT NULL AFTER kein_kontakt,
  ADD COLUMN kein_kontakt_wer      VARCHAR(255) NULL AFTER kein_kontakt_grund,
  ADD COLUMN kein_kontakt_datum    DATE NULL AFTER kein_kontakt_wer;

-- Hauptsponsor zum Paket-Enum hinzufügen
ALTER TABLE sponsors
  MODIFY COLUMN paket ENUM('hauptsponsor','gold','silber','bronze') NULL;

-- Altes Einzel-Feld ansprechpartner + email nicht löschen (Datenmigration später),
-- wird im UI ausgeblendet
