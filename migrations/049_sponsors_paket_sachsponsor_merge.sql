-- 049_sponsors_paket_sachsponsor_merge.sql
-- Phase 1 der Sponsoring-Modell-Vereinheitlichung: der Sponsoring-Typ wird EIN Feld.
-- Schritt 1 (additiv): paket-ENUM um 'sachsponsor' erweitern und die bisherigen Sachsponsor-
-- Zeilen (Boolean-Flag) in den neuen Typ-Wert überführen. Die Spalte sachsponsor bleibt in
-- diesem Schritt noch bestehen (wird erst nach dem Code-Deploy in Migration 050 gedroppt).

ALTER TABLE sponsors
  MODIFY COLUMN paket ENUM('hauptsponsor','gold','silber','bronze','sachsponsor') NULL;

UPDATE sponsors SET paket = 'sachsponsor' WHERE sachsponsor = 1;
