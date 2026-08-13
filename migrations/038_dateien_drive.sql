-- 038_dateien_drive.sql
-- Google-Drive-Storage (intern/gdrive-storage-spec.md, Paket 1):
--   1) dateien: drive_file_id (Drive-Datei-ID) + provider (local|drive) für den
--      Übergang. provider='local' = Altbestand in storage/files/, 'drive' = im
--      geteilten Laufwerk. drive_file_id ist NULL, solange die Datei noch lokal liegt.
--   2) drive_kategorie_ordner: Cache (bereich, kategorie) -> Drive-Ordner-ID. Die App
--      legt je Bereich (Orga/Helfer) und Kategorie (aus dateiKategorien()) idempotent
--      einen Unterordner im geteilten Laufwerk an und merkt sich hier dessen ID (kein
--      manuelles Pflegen von IDs). kategorie='' markiert den Bereichs-Wurzelordner.
-- Nicht-destruktiv (ADD COLUMN / CREATE) -> vor dem Code-Deploy fahren.

-- hochgeladen_von wird nullable: direkt in Drive abgelegte Dateien (Reconciliation,
-- Modell A) haben keinen Dashboard-Uploader. FK bleibt (erlaubt NULL).
ALTER TABLE dateien
  ADD COLUMN drive_file_id VARCHAR(128) NULL AFTER dateiname,
  ADD COLUMN provider ENUM('local','drive') NOT NULL DEFAULT 'local' AFTER drive_file_id,
  MODIFY COLUMN hochgeladen_von INT UNSIGNED NULL;

CREATE TABLE drive_kategorie_ordner (
  bereich         VARCHAR(8)   NOT NULL,
  kategorie       VARCHAR(32)  NOT NULL DEFAULT '',
  drive_folder_id VARCHAR(128) NOT NULL,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (bereich, kategorie)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
