-- 050_drop_sponsors_sachsponsor.sql
-- Phase 1, Schritt 3 (destruktiv): der Sponsoring-Typ ist jetzt allein im paket-Feld
-- (inkl. 'sachsponsor', Migration 049 + Code-Deploy 21d49c3). Der alte Boolean wird nicht
-- mehr von Code gelesen/geschrieben — Spalte entfernen, damit nichts Verwaistes bleibt.

ALTER TABLE sponsors DROP COLUMN sachsponsor;
