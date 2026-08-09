-- 052_sponsor_leistungen.sql
-- Phase 2: per-Sponsor-Zustand der Leistungs-Matrix. Eine Zeile je Sponsor+Position, aber NUR wenn
-- vom Standard abgewichen wird oder ein Freitext/Gutscheincode vorliegt — fehlt die Zeile, gilt der
-- Katalog-Default (vereinbart = Position gilt laut Typ). vereinbart: 1 = Haken, 0 = fällt weg / Extra aus.
-- freitext: Banner-/Startertüten-Text bzw. RaceResult-Gutscheincode bei Startplätzen.

CREATE TABLE sponsor_leistungen (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sponsor_id  INT UNSIGNED NOT NULL,
  position    VARCHAR(40) NOT NULL,
  vereinbart  TINYINT(1) NOT NULL DEFAULT 1,
  freitext    TEXT NULL,
  UNIQUE KEY uq_sponsor_position (sponsor_id, position),
  CONSTRAINT fk_leistung_sponsor FOREIGN KEY (sponsor_id) REFERENCES sponsors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
