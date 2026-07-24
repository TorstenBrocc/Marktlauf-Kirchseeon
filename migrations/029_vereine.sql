-- 029_vereine.sql
-- Neues Modul „Vereine & regionale Laufevents": Kontaktliste + editierbare
-- Anschreiben + Sende-Queue. Analog zum Sponsoren-Stack (006/012/015), aber
-- eigenständig, weil die Beziehung eine andere ist (Mitlaufen / Veranstalter-
-- Vernetzung statt Sponsoring) — kein Paket/keine Summe.
--
-- Einzel-Kontakt-Modell: je Verein/Event GENAU eine Kontaktzeile direkt am
-- Datensatz (entspricht dem Excel-Muster Vereinskontakte). Kein separates
-- Ansprechpartner-1:n wie bei Sponsoren.

CREATE TABLE vereine (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kategorie      ENUM('verein','laufevent') NOT NULL DEFAULT 'verein',
  name           VARCHAR(255) NOT NULL,                 -- Vereins- bzw. Eventname
  veranstalter   VARCHAR(255) NULL,                     -- nur Laufevent: Veranstalter
  ort            VARCHAR(120) NULL,
  entfernung     VARCHAR(32)  NULL,                     -- z. B. "~6 km"
  relevanz       VARCHAR(255) NULL,                     -- Laufsport-Relevanz / Distanzen
  termin         VARCHAR(120) NULL,                     -- nur Laufevent: Termin
  anrede         ENUM('Herr','Frau','Divers','') NOT NULL DEFAULT '',
  vorname        VARCHAR(128) NULL,
  nachname       VARCHAR(128) NULL,
  funktion       VARCHAR(120) NULL,
  email          VARCHAR(255) NULL,
  telefon        VARCHAR(64)  NULL,
  anschrift      VARCHAR(255) NULL,
  website        VARCHAR(255) NULL,
  social         VARCHAR(255) NULL,                     -- Social-Media-Kanäle
  quelle         VARCHAR(255) NULL,                     -- Impressum / Quelle
  hinweis        VARCHAR(500) NULL,
  status         ENUM('neu','angeschrieben','in_kontakt','partner','kein_interesse')
                   NOT NULL DEFAULT 'neu',
  anschreiben_typ ENUM('verein','laufevent') NULL,
  gesendet_am    DATETIME NULL,
  notizen        TEXT NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_vereine_kategorie (kategorie),
  INDEX idx_vereine_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Editierbare Anschreiben (Markdown). Standardtext lebt im Code
-- (src/verein_brief.php -> vereinBriefDefaults()); diese Tabelle hält nur
-- Überschreibungen. Leerer koerper_md => Code-Default greift.
CREATE TABLE verein_briefvorlagen (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug           VARCHAR(32) NOT NULL UNIQUE,
  name           VARCHAR(120) NOT NULL,
  betreff        VARCHAR(255) NULL,
  koerper_md     MEDIUMTEXT NULL,
  aktualisiert_am DATETIME NULL,
  aktualisiert_von INT UNSIGNED NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO verein_briefvorlagen (slug, name) VALUES
  ('verein',    'Vereins-Einladung'),
  ('laufevent', 'Laufevent / Veranstalter-Vernetzung');

-- Sende-Queue für bestätigten Batch-Versand (CLI: bin/verein_versand.php).
-- Snapshot der Empfängerdaten, robust gegen zwischenzeitliche Änderungen.
CREATE TABLE verein_versand_queue (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  verein_id       INT UNSIGNED NOT NULL,
  email           VARCHAR(255) NOT NULL,
  anrede          VARCHAR(16) NOT NULL DEFAULT '',
  vorname         VARCHAR(128) NOT NULL DEFAULT '',
  nachname        VARCHAR(128) NOT NULL DEFAULT '',
  name            VARCHAR(255) NOT NULL DEFAULT '',
  kategorie       ENUM('verein','laufevent') NOT NULL DEFAULT 'verein',
  anschreiben_typ ENUM('verein','laufevent') NOT NULL DEFAULT 'verein',
  status          ENUM('offen','gesendet','fehler') NOT NULL DEFAULT 'offen',
  fehler_text     TEXT NULL,
  angefordert_von INT UNSIGNED NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  gesendet_am     DATETIME NULL,
  CONSTRAINT fk_verein_versand_queue_verein FOREIGN KEY (verein_id) REFERENCES vereine(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
