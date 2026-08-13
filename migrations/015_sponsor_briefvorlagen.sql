-- 015_sponsor_briefvorlagen.sql
-- Editierbare Sponsoren-Anschreiben (Markdown) inkl. dritter, frei gestaltbarer Vorlage.
-- Der Standardtext lebt weiterhin im Code (src/sponsor_brief.php -> sponsorBriefDefaults());
-- diese Tabelle hält nur die Überschreibungen. Leerer koerper_md => Code-Default wird genutzt.

CREATE TABLE sponsor_briefvorlagen (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug           VARCHAR(32) NOT NULL UNIQUE,
  name           VARCHAR(120) NOT NULL,
  betreff        VARCHAR(255) NULL,
  koerper_md     MEDIUMTEXT NULL,
  aktualisiert_am DATETIME NULL,
  aktualisiert_von INT UNSIGNED NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Drei Vorlagen anlegen (Text bleibt leer => Code-Default greift, bis im Editor gespeichert wird).
INSERT INTO sponsor_briefvorlagen (slug, name) VALUES
  ('erstanschreiben', 'Erstanschreiben'),
  ('folgejahr',       'Folgejahr / Bestandssponsor'),
  ('frei',            'Freier Brief');

-- 'frei' als weiteren Anschreiben-Typ zulassen (Tracking + Queue).
ALTER TABLE sponsors
  MODIFY COLUMN anschreiben_typ ENUM('erstanschreiben','folgejahr','frei') NULL;

ALTER TABLE sponsor_versand_queue
  MODIFY COLUMN anschreiben_typ ENUM('erstanschreiben','folgejahr','frei') NOT NULL DEFAULT 'erstanschreiben';
