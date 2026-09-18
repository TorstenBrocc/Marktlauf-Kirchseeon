-- 095_schichten_sortierung.sql
-- Handsortierung des Einsatzplans (Board, Drag & Drop).
--
-- Bis hierher war die Reihenfolge der Schichten allein aus den Daten abgeleitet
-- (tag, von, titel). Der reale Einsatzplan folgt aber einer Sinnfolge, nicht der
-- Uhr: Aufbau -> Anmeldung/Orga -> Radfahrer vorne -> Radfahrer hinten ->
-- Streckenposten -> Versorgung -> Getraenkeverkauf -> Abbau. Viele dieser Posten
-- haben ueberhaupt keine Uhrzeit, die Zeit kann die Ordnung also gar nicht tragen.
--
-- `sortierung` ist ab jetzt die Wahrheit *innerhalb* eines Tages; die
-- Tagesgruppierung bleibt hart an `tag`. Sortiert wird kuenftig nach
--   (tag IS NULL), tag, sortierung, (von IS NULL), von, titel
-- d. h. die Zeit bleibt Tie-Breaker, wenn zwei Schichten denselben Rang haben.
--
-- Backfill: Minuten seit Mitternacht als Startrang. Damit sieht der erste Aufruf
-- exakt aus wie die bisherige Zeitsortierung (Schichten ohne `von` landen wegen
-- 9999 hinten, genau wie das bisherige "(von IS NULL)" es tat) — es springt
-- nichts, sobald die Migration durch ist. Ab dann gewinnt die Hand.
--
-- Default 9999: neu angelegte Schichten landen am Ende ihres Tages, nicht
-- irgendwo in der Mitte. Das Board schreibt beim Umsortieren kompakte Raenge
-- (0..n) fuer den betroffenen Tag zurueck.

SET NAMES utf8mb4;

ALTER TABLE `schichten`
    ADD COLUMN `sortierung` INT UNSIGNED NOT NULL DEFAULT 9999 AFTER `bedarf`,
    ADD KEY `idx_schichten_sortierung` (`tag`, `sortierung`);

UPDATE `schichten`
SET `sortierung` = COALESCE(TIME_TO_SEC(`von`) DIV 60, 9999);
