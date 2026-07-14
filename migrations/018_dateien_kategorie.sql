-- 018_dateien_kategorie.sql
-- Kategorie-Etikett für Dateien (Einordnung + Filter in der Dateiablage).
-- Nicht-destruktiv: bestehende Zeilen erhalten den Default 'allgemein'.

ALTER TABLE dateien
  ADD COLUMN kategorie VARCHAR(50) NOT NULL DEFAULT 'allgemein' AFTER bereich;
