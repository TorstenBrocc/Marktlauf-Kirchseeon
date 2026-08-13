-- 030_sponsoren_rechnung_gruppe_anschreiben.sql
-- Drei Ausbauten der Sponsoren-Übersicht:
--   1) Rechnungsanschrift + eigene Rechnungs-Mailadresse (Übersicht zeigt nur ✓/✗)
--   2) Firmengruppen (Konzern-Tag, z.B. "Ahorn Gruppe" für PIETAS + DENK) — schmal:
--      reines Label, kein Rollup, keine Pflicht zu einer eigenen Parent-Sponsor-Zeile.
--   3) Anschreiben-Versand: statt implizit "erster Ansprechpartner mit E-Mail" ein
--      explizites Flag je Kontakt, ob er ins Anschreiben aufgenommen wird.

CREATE TABLE sponsor_gruppen (
  id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  UNIQUE KEY uq_sponsor_gruppen_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE sponsors
  ADD COLUMN gruppe_id        INT UNSIGNED NULL     AFTER zustaendig_user_id,
  ADD COLUMN rechnung_firma   VARCHAR(255) NULL     AFTER gruppe_id,
  ADD COLUMN rechnung_strasse VARCHAR(255) NULL     AFTER rechnung_firma,
  ADD COLUMN rechnung_plz     VARCHAR(10)  NULL     AFTER rechnung_strasse,
  ADD COLUMN rechnung_ort     VARCHAR(120) NULL     AFTER rechnung_plz,
  ADD COLUMN rechnung_email   VARCHAR(255) NULL     AFTER rechnung_ort;

ALTER TABLE sponsors
  ADD CONSTRAINT fk_sponsor_gruppe
      FOREIGN KEY (gruppe_id) REFERENCES sponsor_gruppen(id) ON DELETE SET NULL;

ALTER TABLE sponsor_ansprechpartner
  ADD COLUMN im_anschreiben TINYINT(1) NOT NULL DEFAULT 1 AFTER email;
