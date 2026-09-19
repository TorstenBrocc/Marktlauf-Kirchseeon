-- 100_postenkoordinaten_aus_streckenplan.sql
-- Die echten Standorte aller 13 Streckenposten -- und die Ruecknahme der
-- A-Kennung aus den Postenbeschreibungen.
--
-- HERKUNFT DER KOORDINATEN
-- Die Punkte stecken im Streckenplan selbst: Streckenplan_Luftbild_A3.html
-- (Ordner Helfer/Einsatzplan) zeichnet Strecken und Posten als SVG in
-- Millimetern. Der 10-km-Pfad dort hat exakt dieselben 216 Stuetzpunkte wie
-- assets/courses/10km.gpx. Ueber diese 216 Paare laesst sich die Abbildung
-- Millimeter -> Grad bestimmen; der Anpassungsfehler liegt bei 0,0 m im Mittel
-- und 0,1 m im Maximum. Damit sind die Postenmarken zurueckgerechnet.
--
-- Drei unabhaengige Gegenproben:
--   * Posten 1, 2 und 4 sind Wendepunkte der Kurzstrecken. Aus den jeweiligen
--     GPX-Dateien getrennt bestimmt, weichen sie 1-2 m von den zurueckgerechneten
--     Werten ab.
--   * Posten 7 und 8 sind im Plan nur in der Ilching-Lupe beschriftet; ihre
--     Marken im Hauptbild tragen keine Nummer. Der Abstand der beiden Marken
--     verhaelt sich zwischen Lupe und Hauptbild wie 4,288 : 1 -- die Lupe ist
--     laut Plan 1:2.475 gegenueber 1:10.600, also 4,283 : 1. Damit ist belegt,
--     welche Marke im Hauptbild zu welcher Nummer gehoert.
--   * Nach Breitengrad liegen die vier Ilching-Posten danach in der Reihenfolge
--     6 > 7 > 8 > 9, also Nord > Mitte-Nord > Mitte-Sued > Sued -- so wie sie heissen.
--
-- Posten 5 wird dabei KORRIGIERT: Migration 097 hatte dort den letzten
-- gemeinsamen Punkt von 5-km- und 10-km-Track gesetzt. Das war falsch -- der
-- 5-km-Kurs liegt vollstaendig auf dem 10-km-Kurs, einen geometrischen
-- Trennpunkt gibt es gar nicht. Der Plan setzt Posten 5 bei km 1; der alte Wert
-- lag rund 700 m daneben.
--
-- MELDEWEG
-- Die Abschnittskennungen A1-A9 gehoeren zur Kommunikationsmatrix fuer das BRK
-- und nicht in die Helfereinweisung. Migration 099 hatte sie in die
-- Postenbeschreibungen geschrieben; das wird hier zurueckgenommen, zurueck auf
-- die Formulierung des Streckenplans.

SET NAMES utf8mb4;


-- 1) Meldeweg zurueck auf die Formulierung des Streckenplans.

UPDATE `schichten`
   SET `beschreibung` = REPLACE(`beschreibung`, 'Standortmeldung immer mit Abschnittskennung (A1–A9) und nächster Kilometermarke, z. B. „Person in A6, kurz nach km 3".', 'Standortmeldung immer mit Lauf und nächster Marke, z. B. „Person auf dem 10 km kurz nach km 6".')
 WHERE `beschreibung` LIKE '%Abschnittskennung%';


-- 2) Standorte aller 13 Posten.

UPDATE `schichten` SET `lat` = 48.078876, `lon` = 11.852902
 WHERE `tag` = '2026-09-20' AND `postennummer` = 1;   -- Wende 500 m

UPDATE `schichten` SET `lat` = 48.077588, `lon` = 11.852315
 WHERE `tag` = '2026-09-20' AND `postennummer` = 2;   -- Wende 1 km

UPDATE `schichten` SET `lat` = 48.075067, `lon` = 11.855945
 WHERE `tag` = '2026-09-20' AND `postennummer` = 3;   -- Wegweiser 2 km

UPDATE `schichten` SET `lat` = 48.075633, `lon` = 11.851789
 WHERE `tag` = '2026-09-20' AND `postennummer` = 4;   -- 2 km Wende

UPDATE `schichten` SET `lat` = 48.074351, `lon` = 11.857522
 WHERE `tag` = '2026-09-20' AND `postennummer` = 5;   -- Trennung 5 km / 10 km

UPDATE `schichten` SET `lat` = 48.070704, `lon` = 11.867771
 WHERE `tag` = '2026-09-20' AND `postennummer` = 6;   -- Verkehr Ilching Nord

UPDATE `schichten` SET `lat` = 48.069994, `lon` = 11.868362
 WHERE `tag` = '2026-09-20' AND `postennummer` = 7;   -- Verkehr Ilching Mitte-Nord

UPDATE `schichten` SET `lat` = 48.069859, `lon` = 11.868519
 WHERE `tag` = '2026-09-20' AND `postennummer` = 8;   -- Verkehr Ilching Mitte-Süd

UPDATE `schichten` SET `lat` = 48.069194, `lon` = 11.868691
 WHERE `tag` = '2026-09-20' AND `postennummer` = 9;   -- Verkehr Ilching Süd

UPDATE `schichten` SET `lat` = 48.062089, `lon` = 11.852873
 WHERE `tag` = '2026-09-20' AND `postennummer` = 10;   -- Vollsperrung Süd + Läuferlenkung

UPDATE `schichten` SET `lat` = 48.060925, `lon` = 11.850815
 WHERE `tag` = '2026-09-20' AND `postennummer` = 11;   -- Vollsperrung Süd Friedhof

UPDATE `schichten` SET `lat` = 48.077559, `lon` = 11.858433
 WHERE `tag` = '2026-09-20' AND `postennummer` = 12;   -- Vollsperrung Nord unten

UPDATE `schichten` SET `lat` = 48.077988, `lon` = 11.858708
 WHERE `tag` = '2026-09-20' AND `postennummer` = 13;   -- Vollsperrung Nord oben
