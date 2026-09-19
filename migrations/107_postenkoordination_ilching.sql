-- 107_postenkoordination_ilching.sql
-- Postenkoordination Ilching als eigener Einsatz.
--
-- In Ilching stehen fuenf Punkte innerhalb von rund 170 Metern (P6-P9 und die
-- Verpflegung V2), alle im selben Zeitfenster. Das Kommunikationsblatt fuehrt
-- dafuer eine eigene Funktion "Postenkoordination Ilching" mit dem Zeitfenster
-- 10:55-12:12 -- bisher ohne Namen. Sie ist jetzt besetzt (Festlegung TT).
--
-- Eigener Eintrag statt eines Zusatzes an der Verpflegungsschicht: die
-- Koordination ist eine zweite Aufgabe, die auch dann gilt, wenn jemand anderes
-- an der Station steht. So steht sie als eigene Karte auf der persoenlichen
-- Seite und ist im Board einzeln verschiebbar.
--
-- Standort = die Verpflegungsstation am Loeschteich; von dort sind P7 und P8
-- wenige Schritte entfernt, P6 und P9 liegen am jeweiligen Ortsende.
--
-- Wer die Rolle uebernimmt, steht als Helfer-ID in der Zuteilung -- das Repo ist
-- oeffentlich, Klarnamen gehoeren nicht hinein.

SET NAMES utf8mb4;

INSERT INTO `schichten`
  (`titel`, `kennung`, `beschreibung`, `ort`, `lat`, `lon`, `tag`, `von`, `bis`, `bedarf`, `in_anmeldung`, `sortierung`)
SELECT 'Postenkoordination Ilching', 'KI',
       'Haelt die fünf Einsatzpunkte in Ilching zusammen: P6 Nord, P7 Mitte-Nord, P8 Mitte-Süd, P9 Süd und die Verpflegung V2. Ansprechpartnerin vor Ort für die Streckenleitung — Rückfragen, Ausfälle, Umbesetzungen laufen über diese Rolle. Standort ist die Verpflegungsstation am Löschteich; P7 und P8 sind von dort wenige Schritte entfernt, P6 und P9 liegen am jeweiligen Ortsende.',
       'Ilching, Löschteich Westseite', 48.069947, 11.868487,
       '2026-09-20', '10:55:00', '12:12:00', 1, 0, 672
  FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id` FROM `schichten`
        WHERE `tag` = '2026-09-20' AND `kennung` = 'KI') AS vorhanden);

-- Zuteilung ueber die Helfer-ID (siehe Kopf).
INSERT INTO `schicht_zuteilung` (`schicht_id`, `helfer_id`)
SELECT s.`id`, 6 FROM `schichten` s
 WHERE s.`tag` = '2026-09-20' AND s.`kennung` = 'KI'
   AND NOT EXISTS (SELECT 1 FROM (SELECT `schicht_id`, `helfer_id` FROM `schicht_zuteilung`) z
                    WHERE z.`schicht_id` = s.`id` AND z.`helfer_id` = 6);

-- Die vier Ilching-Posten erfahren, wer koordiniert.
UPDATE `schichten`
   SET `beschreibung` = CONCAT(`beschreibung`, ' Koordination vor Ort: die Postenkoordination Ilching an der Verpflegungsstation am Löschteich.')
 WHERE `tag` = '2026-09-20' AND `postennummer` BETWEEN 6 AND 9
   AND `beschreibung` NOT LIKE '%Koordination vor Ort%';
