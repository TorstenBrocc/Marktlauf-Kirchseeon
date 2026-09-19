-- 097_schichten_posten_koordinaten.sql
-- Postennummer und Koordinaten an der Schicht.
--
-- Der Helfer braucht am Renntag drei Dinge, die im System bisher fehlen:
-- seine Postennummer (die Orga spricht ueber "Posten 7", nicht ueber
-- "Verkehr Ilching Mitte-Sued"), den genauen Standort zum Hinfinden, und das
-- Zeitfenster. Zeit steckt schon in von/bis; Nummer und Koordinaten kommen hier
-- dazu. Aus lat/lon baut die Helferseite den Google-Maps-Link.
--
-- Bewusst an `schichten` und nicht an einer eigenen Postentabelle: ein Posten
-- IST eine Schicht mit Ort, Zeit und Bedarf — eine zweite Tabelle haette
-- dieselben Felder noch einmal und muesste synchron gehalten werden.
--
-- DECIMAL(9,6): rund 11 cm Aufloesung, mehr als genug fuer einen Treffpunkt,
-- und anders als FLOAT ohne Rundungsueberraschungen.
--
-- Die vier gesetzten Koordinaten sind aus den GPX-Strecken des Laufs abgeleitet
-- (assets/courses/), nicht geschaetzt:
--   * Posten 11 (Wende 500 m) = entferntester Punkt von 500m.gpx
--   * Posten 10 (Wende 1 km)  = entferntester Punkt von 1km.gpx
--   * Posten 5  (Trennung)    = letzter gemeinsamer Punkt von 5km.gpx und 10km.gpx
--   * Versorgung Ilching liegt am Loeschteich West; dafuer gibt es KEINE
--     GPX-Ableitung, deshalb bleibt sie hier leer.
-- Die uebrigen Posten (1-4 Vollsperrung, 6-9 Verkehr Ilching) liegen an
-- Strassenpunkten, die sich aus den Streckendaten nicht ableiten lassen. Sie
-- bleiben NULL, bis die echten Koordinaten nachgetragen werden — die Anzeige
-- zeigt dann einfach keinen Kartenlink statt eines falschen.

SET NAMES utf8mb4;

ALTER TABLE `schichten`
    ADD COLUMN `postennummer` SMALLINT UNSIGNED NULL DEFAULT NULL AFTER `titel`,
    ADD COLUMN `lat` DECIMAL(9,6) NULL DEFAULT NULL AFTER `ort`,
    ADD COLUMN `lon` DECIMAL(9,6) NULL DEFAULT NULL AFTER `lat`,
    ADD KEY `idx_schichten_posten` (`tag`, `postennummer`);

-- Postennummern aus dem Titel ziehen ("Streckenposten 7 · ...").
UPDATE `schichten`
   SET `postennummer` = CAST(
           SUBSTRING_INDEX(SUBSTRING_INDEX(`titel`, 'Streckenposten ', -1), ' ', 1) AS UNSIGNED)
 WHERE `titel` LIKE 'Streckenposten % · %'
   AND `postennummer` IS NULL;

-- Koordinaten, soweit aus den GPX-Strecken belegt.
UPDATE `schichten` SET `lat` = 48.078873, `lon` = 11.852888
 WHERE `tag` = '2026-09-20' AND `titel` = 'Streckenposten 11 · Wende 500 m';

UPDATE `schichten` SET `lat` = 48.077579, `lon` = 11.852297
 WHERE `tag` = '2026-09-20' AND `titel` = 'Streckenposten 10 · Wende 1 km';

UPDATE `schichten` SET `lat` = 48.068010, `lon` = 11.854187
 WHERE `tag` = '2026-09-20' AND `titel` = 'Streckenposten 5 · Trennung 5 km / 10 km';
