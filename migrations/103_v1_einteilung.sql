-- 103_v1_einteilung.sql
-- Einteilung der Verpflegung Start/Ziel (V1) nach der Helferliste vom 19.09.
-- Dort steht unter "Versorgungsstation vor Ort": Raedler Walter und Russ Maria.
-- Die Station in Ilching (V2) ist mit Bayer Sita und Reichmeyer Claudia bereits
-- besetzt (Migration 098).
--
-- Bedarf von 1 auf 2: die Liste nennt zwei Personen, und der Zaehler im Board
-- soll die Station nicht als ueberbesetzt zeigen. V1 steht im Anmeldeformular
-- auf "gesperrt" (in_anmeldung = 2), es laesst sich dort also niemand neu
-- eintragen -- die Maske aendert sich dadurch nicht.

SET NAMES utf8mb4;

UPDATE `schichten` SET `bedarf` = 2
 WHERE `tag` = '2026-09-20' AND `kennung` = 'V1' AND `bedarf` < 2;

INSERT INTO `schicht_zuteilung` (`schicht_id`, `helfer_id`)
SELECT s.`id`, h.`id` FROM `schichten` s JOIN `helfer` h
 WHERE s.`tag` = '2026-09-20' AND s.`kennung` = 'V1'
   AND h.`vorname` = 'Walter' AND h.`nachname` = 'Rädler'
   AND NOT EXISTS (SELECT 1 FROM (SELECT `schicht_id`, `helfer_id` FROM `schicht_zuteilung`) z
                    WHERE z.`schicht_id` = s.`id` AND z.`helfer_id` = h.`id`);

INSERT INTO `schicht_zuteilung` (`schicht_id`, `helfer_id`)
SELECT s.`id`, h.`id` FROM `schichten` s JOIN `helfer` h
 WHERE s.`tag` = '2026-09-20' AND s.`kennung` = 'V1'
   AND h.`vorname` = 'Maria' AND h.`nachname` = 'Russ'
   AND NOT EXISTS (SELECT 1 FROM (SELECT `schicht_id`, `helfer_id` FROM `schicht_zuteilung`) z
                    WHERE z.`schicht_id` = s.`id` AND z.`helfer_id` = h.`id`);
