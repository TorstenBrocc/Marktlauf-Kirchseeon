-- 101_posten_einsatzfenster.sql
-- Einsatzzeiten je Posten: ab wann vor Ort, bis wann bleiben.
--
-- Bisher stand bei den Posten 5-9 nur "waehrend des Laufs". Gerechnet wird jetzt
-- aus der Datei "Marktlauf 2026 - Durchlaufzeiten (Stand 18.09., 10,375 km).xlsx"
-- (Ordner Helfer/Einsatzplan), Blatt 'Parameter' und 'Posten':
--
--   besetzt ab = frueheste Passage eines Erstlaeufers - 15 Minuten Puffer
--                (Puffer ist ein Eingabewert im Blatt 'Parameter')
--   frei ab    = spaeteste Passage im Zeitlimit, also
--                Startzeit + km x (Zielschluss - Start) / Streckenlaenge
--
-- Ein Posten wird von mehreren Laeufen und teils mehrfach passiert; massgeblich
-- ist die frueheste bzw. spaeteste aller Passagen.
--
-- Zwei Ergaenzungen zur Tabelle:
--   * Fuer P3 und P4 steht in der Excel "Passagen aus GPX nachtragen". Sie sind
--     hier aus den Streckendateien gemessen: P3 liegt auf 2 km (km 1,12), 5 km
--     (0,89 / 4,55) und 10 km (0,90 / 9,48), jeweils 1 m neben dem Kurs; P4 nur
--     auf dem 2-km-Kurs (km 0,77) und 204 m vom 10-km-Kurs entfernt -- das deckt
--     sich mit den Notizen in der Excel (1,9 m bzw. 203 m).
--   * Die vier Sperrposten 10-13 halten eine Absperrung, nicht nur das
--     Laeuferfeld: ihr Fenster ist mindestens 10:45-12:30 (VAO-Zeitraum
--     11:00-12:30 plus Aufbau), auch wo die reine Laeuferrechnung kuerzer waere.
--     Bei P10 endete sie rechnerisch 11:52 -- die Absperrung steht bis 12:30.
--
-- Die Werte sind eine RECHNUNG auf Basis angenommener Tempi (Blatt 'Offene
-- Punkte': Zielschluss und Tempi der Kurzstrecken sind gesetzt, nicht belegt).
-- Aendert sich ein Parameter, aendern sich diese Zeiten.

SET NAMES utf8mb4;


UPDATE `schichten` SET `von` = '09:46:00', `bis` = '12:28:00', `zeitfenster` = NULL
 WHERE `tag` = '2026-09-20' AND `postennummer` = 1;   -- Wende 500 m

UPDATE `schichten` SET `von` = '10:17:00', `bis` = '12:26:00', `zeitfenster` = NULL
 WHERE `tag` = '2026-09-20' AND `postennummer` = 2;   -- Wende 1 km

UPDATE `schichten` SET `von` = '10:20:00', `bis` = '12:22:00', `zeitfenster` = NULL
 WHERE `tag` = '2026-09-20' AND `postennummer` = 3;   -- Wegweiser 2 km

UPDATE `schichten` SET `von` = '10:18:00', `bis` = '10:40:00', `zeitfenster` = NULL
 WHERE `tag` = '2026-09-20' AND `postennummer` = 4;   -- 2 km Wende

UPDATE `schichten` SET `von` = '10:49:00', `bis` = '12:21:00', `zeitfenster` = NULL
 WHERE `tag` = '2026-09-20' AND `postennummer` = 5;   -- Trennung 5 km / 10 km

UPDATE `schichten` SET `von` = '10:56:00', `bis` = '12:12:00', `zeitfenster` = NULL
 WHERE `tag` = '2026-09-20' AND `postennummer` = 6;   -- Verkehr Ilching Nord

UPDATE `schichten` SET `von` = '10:56:00', `bis` = '12:11:00', `zeitfenster` = NULL
 WHERE `tag` = '2026-09-20' AND `postennummer` = 7;   -- Verkehr Ilching Mitte-Nord

UPDATE `schichten` SET `von` = '10:56:00', `bis` = '12:11:00', `zeitfenster` = NULL
 WHERE `tag` = '2026-09-20' AND `postennummer` = 8;   -- Verkehr Ilching Mitte-Süd

UPDATE `schichten` SET `von` = '10:55:00', `bis` = '12:10:00', `zeitfenster` = NULL
 WHERE `tag` = '2026-09-20' AND `postennummer` = 9;   -- Verkehr Ilching Süd

UPDATE `schichten` SET `von` = '10:45:00', `bis` = '12:30:00', `zeitfenster` = NULL
 WHERE `tag` = '2026-09-20' AND `postennummer` = 10;   -- Vollsperrung Süd + Läuferlenkung

UPDATE `schichten` SET `von` = '10:45:00', `bis` = '12:30:00', `zeitfenster` = NULL
 WHERE `tag` = '2026-09-20' AND `postennummer` = 11;   -- Vollsperrung Süd Friedhof

UPDATE `schichten` SET `von` = '10:45:00', `bis` = '12:30:00', `zeitfenster` = NULL
 WHERE `tag` = '2026-09-20' AND `postennummer` = 12;   -- Vollsperrung Nord unten

UPDATE `schichten` SET `von` = '10:45:00', `bis` = '12:30:00', `zeitfenster` = NULL
 WHERE `tag` = '2026-09-20' AND `postennummer` = 13;   -- Vollsperrung Nord oben
