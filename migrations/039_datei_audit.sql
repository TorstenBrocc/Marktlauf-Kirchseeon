-- 039_datei_audit.sql
-- Dashboard-Audit-Log für Datei-Aktionen (intern/gdrive-storage-spec.md §2.3).
-- In Drive erscheinen App-Aktionen nur als info@; die echte Person kennt das
-- Dashboard (eingeloggter Nutzer) -> hier festhalten, wer über das Dashboard was
-- getan hat. benutzer_id NULL erlaubt (z.B. automatischer sync/Cron ohne Login).
-- Kein FK auf users: Audit-Historie soll das Löschen eines Nutzers überleben.
-- Nicht-destruktiv (CREATE) -> vor dem Code-Deploy fahren.

CREATE TABLE datei_audit (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  datei_id      INT UNSIGNED NULL,
  drive_file_id VARCHAR(128) NULL,
  originalname  VARCHAR(255) NULL,
  kategorie     VARCHAR(32)  NULL,
  aktion        ENUM('upload','replace','download','delete','sync') NOT NULL,
  benutzer_id   INT UNSIGNED NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_audit_datei (datei_id),
  KEY idx_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
