-- 103_v1_einteilung.sql
-- Einteilung der Verpflegung Start/Ziel (V1) nach der Helferliste vom 19.09.
-- Dort steht unter "Versorgungsstation vor Ort": Helfer #23 und Helfer #22.
-- Die Station in Ilching (V2) ist mit Helfer #6 und Helfer #18 bereits
-- besetzt (Migration 098).
--
-- Bedarf von 1 auf 2: die Liste nennt zwei Personen, und der Zaehler im Board
-- soll die Station nicht als ueberbesetzt zeigen. V1 steht im Anmeldeformular
-- auf "gesperrt" (in_anmeldung = 2), es laesst sich dort also niemand neu
-- eintragen -- die Maske aendert sich dadurch nicht.

-- HINWEIS: Diese Migration nannte urspruenglich Klarnamen. Das Repo ist
-- oeffentlich; die Zuordnung laeuft deshalb ueber Helfer-IDs. Der Datenstand
-- aendert sich dadurch nicht - die Migration ist laengst angewandt, und die
-- IDs treffen dieselben Datensaetze.

SET NAMES utf8mb4;

UPDATE `schichten` SET `bedarf` = 2
 WHERE `tag` = '2026-09-20' AND `kennung` = 'V1' AND `bedarf` < 2;

INSERT INTO `schicht_zuteilung` (`schicht_id`, `helfer_id`)
SELECT s.`id`, h.`id` FROM `schichten` s JOIN `helfer` h
 WHERE s.`tag` = '2026-09-20' AND s.`kennung` = 'V1'
   AND h.`id` = 23
   AND NOT EXISTS (SELECT 1 FROM (SELECT `schicht_id`, `helfer_id` FROM `schicht_zuteilung`) z
                    WHERE z.`schicht_id` = s.`id` AND z.`helfer_id` = h.`id`);

INSERT INTO `schicht_zuteilung` (`schicht_id`, `helfer_id`)
SELECT s.`id`, h.`id` FROM `schichten` s JOIN `helfer` h
 WHERE s.`tag` = '2026-09-20' AND s.`kennung` = 'V1'
   AND h.`id` = 22
   AND NOT EXISTS (SELECT 1 FROM (SELECT `schicht_id`, `helfer_id` FROM `schicht_zuteilung`) z
                    WHERE z.`schicht_id` = s.`id` AND z.`helfer_id` = h.`id`);
