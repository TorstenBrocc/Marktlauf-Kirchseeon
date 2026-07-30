-- branche wird neu als JSON-Array gespeichert (Mehrfachauswahl).
-- Bestehende Einzelwerte werden in ein einelementiges Array gewandelt.
ALTER TABLE sponsors MODIFY COLUMN branche TEXT NULL;

UPDATE sponsors
SET branche = JSON_ARRAY(branche)
WHERE branche IS NOT NULL
  AND branche != ''
  AND LEFT(branche, 1) != '[';
