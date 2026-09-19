-- 099_streckenposten_nummerierung_korrektur.sql
-- Die Postennummern folgen ab jetzt der gueltigen Fassung des Streckenplans
-- (Ordner "Marktlauf Orga/Helfer/Einsatzplan"), nicht mehr der frueheren.
--
-- Migration 096 hat die Posten aus einer aelteren Planfassung uebernommen, die
-- von Norden nach Sueden durchzaehlte und bei 11 endete. Die gueltige Fassung
-- zaehlt genau andersherum -- von Start/Ziel weg ueber Ilching zu den
-- Vollsperrungen -- und kennt 13 Posten: neu dazu kommen "Wegweiser 2 km" (3)
-- und "2 km Wende" (4). Dadurch lag jede Nummer falsch: Posten 7 war
-- "Ilching Mitte-Sued", ist aber "Ilching Mitte-Nord".
--
-- Das ist mehr als Kosmetik: Die Helferliste teilt die Leute ueber NUMMERN ein.
-- Solange Nummer und Aufgabe auseinanderliefen, stand auf jeder Helferseite der
-- falsche Standort. Deshalb werden hier zuerst die Nummern richtiggestellt und
-- danach ALLE Postenzuteilungen aus 098 verworfen und nach der aktuellen
-- Helferliste (Stand 19.09.2026) neu gesetzt.
--
-- Die Aufgabe bleibt jeweils dieselbe Zeile -- Zeiten, Ort, Koordinaten und
-- Beschreibung haengen an der AUFGABE, nicht an der Nummer, und wandern
-- deshalb korrekt mit. Identifiziert wird ueber den Aufgabenteil des Titels.
--
-- Ausserdem: Der Meldeweg heisst in der aktuellen Unterlage
-- "Kommunikation und Streckenabschnitte" (Stand 19.09.) Abschnittskennung
-- A1-A9 plus Kilometermarke, nicht mehr Laufstrecke plus Marke. Der Hinweis in
-- den Beschreibungen wird entsprechend ersetzt.
--
-- Posten 8 (Verkehr Ilching Mitte-Sued) bleibt unbesetzt -- in der Helferliste
-- steht dort "?".

-- HINWEIS: Diese Migration nannte urspruenglich Klarnamen. Das Repo ist
-- oeffentlich; die Zuordnung laeuft deshalb ueber Helfer-IDs. Der Datenstand
-- aendert sich dadurch nicht - die Migration ist laengst angewandt, und die
-- IDs treffen dieselben Datensaetze.

SET NAMES utf8mb4;


-- 1) Alle Zuteilungen an Streckenposten verwerfen. Sie stammen aus 098 und
--    haengen durchweg an der falschen Aufgabe.
DELETE sz FROM `schicht_zuteilung` sz
  JOIN `schichten` s ON s.`id` = sz.`schicht_id`
 WHERE s.`postennummer` IS NOT NULL;


-- 2) Nummern zunaechst leeren: die Umnummerierung vertauscht Werte paarweise,
--    ein Zwischenstand mit doppelten Nummern soll gar nicht erst entstehen.
UPDATE `schichten` SET `postennummer` = NULL WHERE `postennummer` IS NOT NULL;


-- 3) Die beiden Posten, die in der alten Planfassung fehlten.
INSERT INTO `schichten` (`titel`, `beschreibung`, `ort`, `tag`, `von`, `bis`, `zeitfenster`, `bedarf`, `in_anmeldung`, `sortierung`)
SELECT 'Streckenposten 3 · Wegweiser 2 km',
       'Warnweste tragen. Wegweiser fuer den 2-km-Kurs (Start 10:30) — Laeufer an dieser Stelle richtig weiterschicken. Standortmeldung immer mit Abschnittskennung (A1–A9) und nächster Kilometermarke, z. B. „Person in A6, kurz nach km 3".',
       NULL, '2026-09-20', '10:30:00', NULL, NULL, 1, 0, 662
  FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id` FROM `schichten`
        WHERE `tag` = '2026-09-20' AND `titel` LIKE '%· Wegweiser 2 km') AS vorhanden);

INSERT INTO `schichten` (`titel`, `beschreibung`, `ort`, `tag`, `von`, `bis`, `zeitfenster`, `bedarf`, `in_anmeldung`, `sortierung`)
SELECT 'Streckenposten 4 · 2 km Wende',
       'Warnweste tragen. Wendepunkt des 2-km-Kurses (Start 10:30). Laeufer sicher wenden lassen. Standortmeldung immer mit Abschnittskennung (A1–A9) und nächster Kilometermarke, z. B. „Person in A6, kurz nach km 3".',
       NULL, '2026-09-20', '10:30:00', NULL, NULL, 1, 0, 663
  FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id` FROM `schichten`
        WHERE `tag` = '2026-09-20' AND `titel` LIKE '%· 2 km Wende') AS vorhanden);


-- 4) Nummer und Titel je Aufgabe auf die gueltige Fassung setzen.

UPDATE `schichten`
   SET `postennummer` = 1,
       `titel` = 'Streckenposten 1 · Wende 500 m',
       `sortierung` = 660
 WHERE `tag` = '2026-09-20' AND `titel` LIKE '%· Wende 500 m';

UPDATE `schichten`
   SET `postennummer` = 2,
       `titel` = 'Streckenposten 2 · Wende 1 km',
       `sortierung` = 661
 WHERE `tag` = '2026-09-20' AND `titel` LIKE '%· Wende 1 km';

UPDATE `schichten`
   SET `postennummer` = 5,
       `titel` = 'Streckenposten 5 · Trennung 5 km / 10 km',
       `sortierung` = 664
 WHERE `tag` = '2026-09-20' AND `titel` LIKE '%· Trennung 5 km / 10 km';

UPDATE `schichten`
   SET `postennummer` = 6,
       `titel` = 'Streckenposten 6 · Verkehr Ilching Nord',
       `sortierung` = 665
 WHERE `tag` = '2026-09-20' AND `titel` LIKE '%· Verkehr Ilching Nord';

UPDATE `schichten`
   SET `postennummer` = 7,
       `titel` = 'Streckenposten 7 · Verkehr Ilching Mitte-Nord',
       `sortierung` = 666
 WHERE `tag` = '2026-09-20' AND `titel` LIKE '%· Verkehr Ilching Mitte-Nord';

UPDATE `schichten`
   SET `postennummer` = 8,
       `titel` = 'Streckenposten 8 · Verkehr Ilching Mitte-Süd',
       `sortierung` = 667
 WHERE `tag` = '2026-09-20' AND `titel` LIKE '%· Verkehr Ilching Mitte-Süd';

UPDATE `schichten`
   SET `postennummer` = 9,
       `titel` = 'Streckenposten 9 · Verkehr Ilching Süd',
       `sortierung` = 668
 WHERE `tag` = '2026-09-20' AND `titel` LIKE '%· Verkehr Ilching Süd';

UPDATE `schichten`
   SET `postennummer` = 10,
       `titel` = 'Streckenposten 10 · Vollsperrung Süd + Läuferlenkung',
       `sortierung` = 669
 WHERE `tag` = '2026-09-20' AND `titel` LIKE '%· Vollsperrung Süd + Läuferlenkung';

UPDATE `schichten`
   SET `postennummer` = 11,
       `titel` = 'Streckenposten 11 · Vollsperrung Süd Friedhof',
       `sortierung` = 670
 WHERE `tag` = '2026-09-20' AND `titel` LIKE '%· Vollsperrung Süd Friedhof';

UPDATE `schichten`
   SET `postennummer` = 12,
       `titel` = 'Streckenposten 12 · Vollsperrung Nord unten',
       `sortierung` = 671
 WHERE `tag` = '2026-09-20' AND `titel` LIKE '%· Vollsperrung Nord unten';

UPDATE `schichten`
   SET `postennummer` = 13,
       `titel` = 'Streckenposten 13 · Vollsperrung Nord oben',
       `sortierung` = 672
 WHERE `tag` = '2026-09-20' AND `titel` LIKE '%· Vollsperrung Nord oben';

UPDATE `schichten` SET `postennummer` = 3
 WHERE `tag` = '2026-09-20' AND `titel` LIKE '%· Wegweiser 2 km';
UPDATE `schichten` SET `postennummer` = 4
 WHERE `tag` = '2026-09-20' AND `titel` LIKE '%· 2 km Wende';


-- 5) Meldeweg auf die aktuelle Unterlage umstellen (Abschnittskennung statt Lauf).
UPDATE `schichten`
   SET `beschreibung` = REPLACE(`beschreibung`, 'Standortmeldung immer mit Lauf und nächster Marke, z. B. „Person auf dem 10 km kurz nach km 6".', 'Standortmeldung immer mit Abschnittskennung (A1–A9) und nächster Kilometermarke, z. B. „Person in A6, kurz nach km 3".')
 WHERE `beschreibung` LIKE '%Standortmeldung immer mit Lauf%';

UPDATE `schichten`
   SET `beschreibung` = REPLACE(`beschreibung`, 'Standortmeldung immer mit Lauf und nächster Marke.', 'Standortmeldung immer mit Abschnittskennung (A1–A9) und nächster Kilometermarke, z. B. „Person in A6, kurz nach km 3".')
 WHERE `beschreibung` LIKE '%Standortmeldung immer mit Lauf und nächster Marke.%';


-- 6) Einteilung nach der Helferliste (Stand 19.09.2026), Nummern der gueltigen Fassung.

-- Posten 1: Helfer #8
INSERT INTO `schicht_zuteilung` (`schicht_id`, `helfer_id`)
SELECT s.`id`, 8 FROM `schichten` s
 WHERE s.`tag` = '2026-09-20' AND s.`postennummer` = 1
   AND NOT EXISTS (SELECT 1 FROM (SELECT `schicht_id`, `helfer_id` FROM `schicht_zuteilung`) z
                    WHERE z.`schicht_id` = s.`id` AND z.`helfer_id` = 8);

-- Posten 2: Helfer #8
INSERT INTO `schicht_zuteilung` (`schicht_id`, `helfer_id`)
SELECT s.`id`, 8 FROM `schichten` s
 WHERE s.`tag` = '2026-09-20' AND s.`postennummer` = 2
   AND NOT EXISTS (SELECT 1 FROM (SELECT `schicht_id`, `helfer_id` FROM `schicht_zuteilung`) z
                    WHERE z.`schicht_id` = s.`id` AND z.`helfer_id` = 8);

-- Posten 3: Helfer #19
INSERT INTO `schicht_zuteilung` (`schicht_id`, `helfer_id`)
SELECT s.`id`, 19 FROM `schichten` s
 WHERE s.`tag` = '2026-09-20' AND s.`postennummer` = 3
   AND NOT EXISTS (SELECT 1 FROM (SELECT `schicht_id`, `helfer_id` FROM `schicht_zuteilung`) z
                    WHERE z.`schicht_id` = s.`id` AND z.`helfer_id` = 19);

-- Posten 4: Helfer #24
INSERT INTO `schicht_zuteilung` (`schicht_id`, `helfer_id`)
SELECT s.`id`, 24 FROM `schichten` s
 WHERE s.`tag` = '2026-09-20' AND s.`postennummer` = 4
   AND NOT EXISTS (SELECT 1 FROM (SELECT `schicht_id`, `helfer_id` FROM `schicht_zuteilung`) z
                    WHERE z.`schicht_id` = s.`id` AND z.`helfer_id` = 24);

-- Posten 5: Helfer #28
INSERT INTO `schicht_zuteilung` (`schicht_id`, `helfer_id`)
SELECT s.`id`, 28 FROM `schichten` s
 WHERE s.`tag` = '2026-09-20' AND s.`postennummer` = 5
   AND NOT EXISTS (SELECT 1 FROM (SELECT `schicht_id`, `helfer_id` FROM `schicht_zuteilung`) z
                    WHERE z.`schicht_id` = s.`id` AND z.`helfer_id` = 28);

-- Posten 6: Helfer #19
INSERT INTO `schicht_zuteilung` (`schicht_id`, `helfer_id`)
SELECT s.`id`, 19 FROM `schichten` s
 WHERE s.`tag` = '2026-09-20' AND s.`postennummer` = 6
   AND NOT EXISTS (SELECT 1 FROM (SELECT `schicht_id`, `helfer_id` FROM `schicht_zuteilung`) z
                    WHERE z.`schicht_id` = s.`id` AND z.`helfer_id` = 19);

-- Posten 7: Helfer #30
INSERT INTO `schicht_zuteilung` (`schicht_id`, `helfer_id`)
SELECT s.`id`, 30 FROM `schichten` s
 WHERE s.`tag` = '2026-09-20' AND s.`postennummer` = 7
   AND NOT EXISTS (SELECT 1 FROM (SELECT `schicht_id`, `helfer_id` FROM `schicht_zuteilung`) z
                    WHERE z.`schicht_id` = s.`id` AND z.`helfer_id` = 30);

-- Posten 9: Helfer #24
INSERT INTO `schicht_zuteilung` (`schicht_id`, `helfer_id`)
SELECT s.`id`, 24 FROM `schichten` s
 WHERE s.`tag` = '2026-09-20' AND s.`postennummer` = 9
   AND NOT EXISTS (SELECT 1 FROM (SELECT `schicht_id`, `helfer_id` FROM `schicht_zuteilung`) z
                    WHERE z.`schicht_id` = s.`id` AND z.`helfer_id` = 24);

-- Posten 10: Helfer #31
INSERT INTO `schicht_zuteilung` (`schicht_id`, `helfer_id`)
SELECT s.`id`, 31 FROM `schichten` s
 WHERE s.`tag` = '2026-09-20' AND s.`postennummer` = 10
   AND NOT EXISTS (SELECT 1 FROM (SELECT `schicht_id`, `helfer_id` FROM `schicht_zuteilung`) z
                    WHERE z.`schicht_id` = s.`id` AND z.`helfer_id` = 31);

-- Posten 11: Helfer #15
INSERT INTO `schicht_zuteilung` (`schicht_id`, `helfer_id`)
SELECT s.`id`, 15 FROM `schichten` s
 WHERE s.`tag` = '2026-09-20' AND s.`postennummer` = 11
   AND NOT EXISTS (SELECT 1 FROM (SELECT `schicht_id`, `helfer_id` FROM `schicht_zuteilung`) z
                    WHERE z.`schicht_id` = s.`id` AND z.`helfer_id` = 15);

-- Posten 12: Helfer #27
INSERT INTO `schicht_zuteilung` (`schicht_id`, `helfer_id`)
SELECT s.`id`, 27 FROM `schichten` s
 WHERE s.`tag` = '2026-09-20' AND s.`postennummer` = 12
   AND NOT EXISTS (SELECT 1 FROM (SELECT `schicht_id`, `helfer_id` FROM `schicht_zuteilung`) z
                    WHERE z.`schicht_id` = s.`id` AND z.`helfer_id` = 27);

-- Posten 13: Helfer #26
INSERT INTO `schicht_zuteilung` (`schicht_id`, `helfer_id`)
SELECT s.`id`, 26 FROM `schichten` s
 WHERE s.`tag` = '2026-09-20' AND s.`postennummer` = 13
   AND NOT EXISTS (SELECT 1 FROM (SELECT `schicht_id`, `helfer_id` FROM `schicht_zuteilung`) z
                    WHERE z.`schicht_id` = s.`id` AND z.`helfer_id` = 26);
