-- 035_sponsor_rechnungen.sql
-- Sponsoring-Rechnungen (§14 UStG) für das Sponsoren-CRM.
--   1) Zwei neue Felder am Sponsor: konkrete Leistungsbeschreibung + Leistungszeitraum
--      (Pflichtangaben, die bisher fehlten; siehe Rechnungs-Spec).
--   2) Tabelle sponsor_rechnungen: je erzeugter Rechnung ein Datensatz mit einem
--      SNAPSHOT der Rechnungsdaten zum Erstellzeitpunkt (spätere Sponsor-Änderungen
--      verändern eine bereits erzeugte Rechnung nicht). Die fortlaufende Nummer bleibt
--      NULL, bis der Kassier sie im Dashboard vergibt (Status entwurf -> nummeriert).

ALTER TABLE sponsors
  ADD COLUMN rechnung_leistung TEXT         NULL AFTER rechnung_email,
  ADD COLUMN leistung_zeitraum VARCHAR(120) NULL AFTER rechnung_leistung;

CREATE TABLE sponsor_rechnungen (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sponsor_id         INT UNSIGNED NOT NULL,

  -- Fortlaufende Nummer, Format NN-JJJJ. NULL = Entwurf, wartet auf den Kassier.
  -- UNIQUE erlaubt in MySQL mehrere NULLs (viele Entwürfe), verhindert aber
  -- die doppelte Vergabe einer echten Nummer.
  rechnungsnummer    VARCHAR(20) NULL,

  -- Snapshot der Rechnungsdaten zum Erstellzeitpunkt
  empfaenger_firma   VARCHAR(255) NOT NULL,
  empfaenger_strasse VARCHAR(255) NULL,
  empfaenger_plz     VARCHAR(10)  NULL,
  empfaenger_ort     VARCHAR(120) NULL,
  leistung           TEXT         NOT NULL,
  zeitraum           VARCHAR(120) NOT NULL,
  netto              DECIMAL(10,2) NOT NULL,
  ust_satz           DECIMAL(4,2)  NOT NULL DEFAULT 19.00,
  ust_betrag         DECIMAL(10,2) NOT NULL,
  brutto             DECIMAL(10,2) NOT NULL,

  status             ENUM('entwurf','nummeriert') NOT NULL DEFAULT 'entwurf',

  -- Audit-Trail: wer hat den Entwurf erzeugt, wer hat die Nummer vergeben
  erstellt_von       INT UNSIGNED NULL,
  erstellt_am        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  nummer_von         INT UNSIGNED NULL,
  nummer_am          DATETIME     NULL,

  pdf_datei          VARCHAR(255) NULL,

  UNIQUE KEY uq_srech_nummer (rechnungsnummer),
  KEY idx_srech_sponsor (sponsor_id),
  KEY idx_srech_status (status),

  CONSTRAINT fk_srech_sponsor    FOREIGN KEY (sponsor_id)   REFERENCES sponsors(id) ON DELETE CASCADE,
  CONSTRAINT fk_srech_erstellt   FOREIGN KEY (erstellt_von) REFERENCES users(id)    ON DELETE SET NULL,
  CONSTRAINT fk_srech_nummer_von FOREIGN KEY (nummer_von)   REFERENCES users(id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
