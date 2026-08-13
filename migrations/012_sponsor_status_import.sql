-- 012_sponsor_status_import.sql
-- Sponsor-CRM-Ausbau: Status-Lebenszyklus (Ampel), Import-/Versand-Felder, Sende-Queue
-- Grundlage: intern/sponsor-crm-ausbau.md §2

-- 2.1 Status-ENUM auf 6-Werte-Lebenszyklus (Ampel) erweitern.
-- Bestandswerte bleiben gültig:
--   neu(grau) → angefragt/"Angeschrieben"(blau) → in_klaerung/"In Klärung"(gelb)
--   → zugesagt(grün) · bezahlt(grün) · abgelehnt(rot)
-- "Wieder anschreiben" ist kein eigener Status, sondern in_klaerung + gesetztes
-- wiedervorlage-Datum. 'neu' wird neuer Default (importiert, noch nicht angeschrieben).
ALTER TABLE sponsors
  MODIFY COLUMN status
    ENUM('neu','angefragt','in_klaerung','zugesagt','bezahlt','abgelehnt')
    NOT NULL DEFAULT 'neu';

-- 2.2 Neue Spalten für CSV-Felder ohne Zuhause + Versand-Tracking
ALTER TABLE sponsors
  ADD COLUMN prioritaet     TINYINT NULL AFTER paket,
  ADD COLUMN ort            VARCHAR(120) NULL AFTER prioritaet,
  ADD COLUMN gesendet_am    DATETIME NULL AFTER wiedervorlage,
  ADD COLUMN anschreiben_typ ENUM('erstanschreiben','folgejahr') NULL AFTER gesendet_am;

-- 5. Sende-Queue für bestätigten Batch-Versand (CLI: bin/sponsor_versand.php).
-- Snapshot der Empfängerdaten, damit der CLI-Lauf robust gegen zwischenzeitliche
-- Änderungen bleibt.
CREATE TABLE sponsor_versand_queue (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sponsor_id      INT UNSIGNED NOT NULL,
  email           VARCHAR(255) NOT NULL,
  anrede          VARCHAR(16) NOT NULL DEFAULT '',
  nachname        VARCHAR(128) NOT NULL DEFAULT '',
  firma           VARCHAR(255) NOT NULL DEFAULT '',
  paket           VARCHAR(20) NULL,
  anschreiben_typ ENUM('erstanschreiben','folgejahr') NOT NULL DEFAULT 'erstanschreiben',
  status          ENUM('offen','gesendet','fehler') NOT NULL DEFAULT 'offen',
  fehler_text     TEXT NULL,
  angefordert_von INT UNSIGNED NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  gesendet_am     DATETIME NULL,
  CONSTRAINT fk_versand_queue_sponsor FOREIGN KEY (sponsor_id) REFERENCES sponsors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
