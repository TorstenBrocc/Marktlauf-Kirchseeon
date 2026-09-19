-- 102_versorgungsstationen_v1_v2.sql
-- Die beiden Versorgungsstationen bekommen, was die Streckenposten schon haben:
-- Kennung, Standort, Zeitfenster und eine Aufgabenbeschreibung.
--
-- Bisher stand V2 ("Versorgung + Sanitaet Ilching") ohne Koordinate und ohne
-- Zeit in der Liste, V1 existierte nur als Aufbau-Schicht. Wer dort eingeteilt
-- ist, konnte auf seiner Helferseite weder sehen, wo die Station steht, noch ab
-- wann sie besetzt sein muss -- bei V2 in Ilching ist genau das die Information,
-- die zaehlt: der Loeschteich ist von der Ortsdurchfahrt aus nicht offensichtlich.
--
-- NEUE SPALTE `kennung`
-- Die Streckenposten tragen Nummern (1-13), die Stationen tragen Buchstaben
-- (V1, V2). Beides in `postennummer` zu pressen ginge nicht; die Anzeige nimmt
-- ab jetzt `kennung`, wenn sie gesetzt ist, sonst "POSTEN <Nummer>".
--
-- ZEITEN -- gerechnet wie bei den Posten, aus Blatt 'Parameter' und
-- 'Versorgung & Sanitaet' der Durchlaufzeiten-Datei:
--   V1 Start/Ziel: 09:45 - 12:30  (10 Passagen, alle fuenf Laeufe)
--   V2 Ilching:    10:56 - 12:11  (3 Passagen: 5 km km 3,27 / 10 km km 3,28 und 8,21)
-- Das deckt sich mit dem Kommunikationsblatt (dort 09:45-12:30 und 10:55-12:11).
--
-- V1 BEHAELT sein Schichtfenster 09:00-13:00: dort steckt der Auf- und Abbau,
-- der frueher beginnt und spaeter endet als der Betrieb. Die Betriebszeit steht
-- stattdessen in der Beschreibung. Bei V2 gab es gar kein Fenster, dort wird die
-- Betriebszeit gesetzt.
--
-- TITEL V2 praezisiert: aus "Versorgung + Sanitaet Ilching (V2/S2)" wird
-- "Verpflegung Ilching (V2)". Die Sanitaetsstation S2 am selben Punkt stellt das
-- BRK, nicht der ATSV -- der alte Titel konnte den Eindruck erwecken, unsere
-- Helfer haetten dort auch die Sanitaet zu verantworten.
--
-- Warnwestenpflicht gilt laut VAO-Auflage 9 fuer Streckenposten; die
-- Versorgungsstationen sind davon ausgenommen (Blatt 'Versorgung & Sanitaet').
-- Das steht jetzt ausdruecklich in der Beschreibung, damit niemand sucht.

SET NAMES utf8mb4;

ALTER TABLE `schichten`
    ADD COLUMN `kennung` VARCHAR(12) NULL DEFAULT NULL AFTER `postennummer`;

-- V2 Ilching, Loeschteich Westseite
UPDATE `schichten`
   SET `kennung`      = 'V2',
       `titel`        = 'Verpflegung Ilching (V2)',
       `ort`          = 'Ilching, Löschteich Westseite',
       `lat`          = 48.069947,
       `lon`          = 11.868487,
       `von`          = '10:56:00',
       `bis`          = '12:11:00',
       `zeitfenster`  = NULL,
       `beschreibung` = 'Verpflegungsstation am Löschteich (Westseite), rund 4 m neben der Laufstrecke. KEINE Warnwestenpflicht — die gilt nur für die Streckenposten. Der Sanitätsdienst S2 des BRK steht am selben Punkt; die Sanitätsversorgung ist deren Aufgabe, nicht unsere. Die Läufer kommen hier dreimal vorbei: 5 km bei km 3,3 sowie 10 km bei km 3,3 und km 8,2.'
 WHERE `tag` = '2026-09-20'
   AND (`titel` LIKE '%Ilching (V2%' OR `titel` LIKE 'Versorgung + Sanit%Ilching%');

-- V1 Start/Ziel (Hauptstation). Schichtfenster bleibt 09:00-13:00 (Auf-/Abbau).
UPDATE `schichten`
   SET `kennung`      = 'V1',
       `ort`          = 'Start/Ziel, JEK Westring 6',
       `lat`          = 48.079848,
       `lon`          = 11.855253,
       `beschreibung` = CONCAT(
            COALESCE(NULLIF(`beschreibung`, ''), ''),
            IF(`beschreibung` IS NULL OR `beschreibung` = '', '', '\n\n'),
            'Hauptverpflegung am Start/Ziel. Im Betrieb besetzt von 09:45 bis 12:30 Uhr — davor Aufbau, danach Abbau. KEINE Warnwestenpflicht. Die Sanitäts-Hauptstation S1 des BRK steht am selben Punkt.')
 WHERE `tag` = '2026-09-20'
   AND `titel` LIKE '%Versorgungsstation Start/Ziel%'
   AND (`kennung` IS NULL OR `kennung` <> 'V1');
