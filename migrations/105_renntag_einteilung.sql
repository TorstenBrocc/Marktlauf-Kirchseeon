-- 105_renntag_einteilung.sql
-- Die uebrigen Einsaetze des Renntags aus der Helferliste vom 19.09.:
-- Getraenkeverkauf (drei Zeitfenster) und Startnummernausgabe.
--
-- Diese Leute standen in der Liste, hatten im System aber keinen Einsatz — ihre
-- persoenliche Seite haette am Lauftag "Der Einsatzplan wird noch erstellt"
-- gezeigt, obwohl sie fest eingeplant sind.
--
-- Der Bedarf wird auf die tatsaechliche Besetzung gezogen (zwei bzw. drei statt
-- eins), damit der Zaehler im Board nicht faelschlich Ueberbesetzung meldet. Das
-- ist hier gefahrlos: alle vier Schichten stehen im Anmeldeformular auf
-- "gesperrt" (in_anmeldung = 2), es laesst sich dort niemand mehr eintragen —
-- die Maske aendert sich nicht.
--
-- NICHT enthalten: Auf- und Abbau. Diese Schichten sind im Formular noch
-- buchbar (in_anmeldung = 1); eine Bedarfsaenderung wuerde dort Plaetze oeffnen.
-- Ausserdem fehlen drei der sieben Personen aus dem Auf-/Abbau-Block als
-- Datensatz (Gramueller Matthias, Reinhart Stefan, Tyras Torsten) — ohne
-- Datensatz keine Zuteilung und keine persoenliche Seite.

SET NAMES utf8mb4;

UPDATE `schichten` SET `bedarf` = 2
 WHERE `tag` = '2026-09-20' AND `in_anmeldung` = 2 AND `bedarf` < 2
   AND `titel` LIKE '%Getränkeverkauf%';

UPDATE `schichten` SET `bedarf` = 3
 WHERE `tag` = '2026-09-20' AND `in_anmeldung` = 2 AND `bedarf` < 3
   AND `titel` LIKE 'Startnummernausgabe%';

-- Einteilung nach der Helferliste vom 19.09.

INSERT INTO `schicht_zuteilung` (`schicht_id`, `helfer_id`)
SELECT s.`id`, h.`id` FROM `schichten` s JOIN `helfer` h
 WHERE s.`tag` = '2026-09-20' AND s.`von` = '09:30:00' AND s.`titel` LIKE '%Getränkeverkauf%'
   AND h.`vorname` = 'Olivia' AND h.`nachname` = 'Petrasova'
   AND NOT EXISTS (SELECT 1 FROM (SELECT `schicht_id`, `helfer_id` FROM `schicht_zuteilung`) z
                    WHERE z.`schicht_id` = s.`id` AND z.`helfer_id` = h.`id`);

INSERT INTO `schicht_zuteilung` (`schicht_id`, `helfer_id`)
SELECT s.`id`, h.`id` FROM `schichten` s JOIN `helfer` h
 WHERE s.`tag` = '2026-09-20' AND s.`von` = '09:30:00' AND s.`titel` LIKE '%Getränkeverkauf%'
   AND h.`vorname` = 'Alena' AND h.`nachname` = 'Petrasova'
   AND NOT EXISTS (SELECT 1 FROM (SELECT `schicht_id`, `helfer_id` FROM `schicht_zuteilung`) z
                    WHERE z.`schicht_id` = s.`id` AND z.`helfer_id` = h.`id`);

INSERT INTO `schicht_zuteilung` (`schicht_id`, `helfer_id`)
SELECT s.`id`, h.`id` FROM `schichten` s JOIN `helfer` h
 WHERE s.`tag` = '2026-09-20' AND s.`von` = '12:00:00' AND s.`titel` LIKE '%Getränkeverkauf%'
   AND h.`vorname` = 'Andrea' AND h.`nachname` = 'Bauer'
   AND NOT EXISTS (SELECT 1 FROM (SELECT `schicht_id`, `helfer_id` FROM `schicht_zuteilung`) z
                    WHERE z.`schicht_id` = s.`id` AND z.`helfer_id` = h.`id`);

INSERT INTO `schicht_zuteilung` (`schicht_id`, `helfer_id`)
SELECT s.`id`, h.`id` FROM `schichten` s JOIN `helfer` h
 WHERE s.`tag` = '2026-09-20' AND s.`von` = '12:00:00' AND s.`titel` LIKE '%Getränkeverkauf%'
   AND h.`vorname` = 'Charlotte' AND h.`nachname` = 'Bauer'
   AND NOT EXISTS (SELECT 1 FROM (SELECT `schicht_id`, `helfer_id` FROM `schicht_zuteilung`) z
                    WHERE z.`schicht_id` = s.`id` AND z.`helfer_id` = h.`id`);

INSERT INTO `schicht_zuteilung` (`schicht_id`, `helfer_id`)
SELECT s.`id`, h.`id` FROM `schichten` s JOIN `helfer` h
 WHERE s.`tag` = '2026-09-20' AND s.`von` = '14:00:00' AND s.`titel` LIKE '%Getränkeverkauf%'
   AND h.`vorname` = 'Jenny' AND h.`nachname` = 'Fischer'
   AND NOT EXISTS (SELECT 1 FROM (SELECT `schicht_id`, `helfer_id` FROM `schicht_zuteilung`) z
                    WHERE z.`schicht_id` = s.`id` AND z.`helfer_id` = h.`id`);

INSERT INTO `schicht_zuteilung` (`schicht_id`, `helfer_id`)
SELECT s.`id`, h.`id` FROM `schichten` s JOIN `helfer` h
 WHERE s.`tag` = '2026-09-20' AND s.`von` = '08:00:00' AND s.`titel` LIKE '%Startnummernausgabe%'
   AND h.`vorname` = 'Christine' AND h.`nachname` = 'Bullinger'
   AND NOT EXISTS (SELECT 1 FROM (SELECT `schicht_id`, `helfer_id` FROM `schicht_zuteilung`) z
                    WHERE z.`schicht_id` = s.`id` AND z.`helfer_id` = h.`id`);

INSERT INTO `schicht_zuteilung` (`schicht_id`, `helfer_id`)
SELECT s.`id`, h.`id` FROM `schichten` s JOIN `helfer` h
 WHERE s.`tag` = '2026-09-20' AND s.`von` = '08:00:00' AND s.`titel` LIKE '%Startnummernausgabe%'
   AND h.`vorname` = 'Jenny' AND h.`nachname` = 'Fischer'
   AND NOT EXISTS (SELECT 1 FROM (SELECT `schicht_id`, `helfer_id` FROM `schicht_zuteilung`) z
                    WHERE z.`schicht_id` = s.`id` AND z.`helfer_id` = h.`id`);

-- Ohlberger Markus: der Datensatz traegt einen abgeschnittenen Namen ("Marku Ohlb", id 10).
-- Hier nur die Zuteilung; den Namen selbst ruehrt diese Migration nicht an.
INSERT INTO `schicht_zuteilung` (`schicht_id`, `helfer_id`)
SELECT s.`id`, 10 FROM `schichten` s
 WHERE s.`tag` = '2026-09-20' AND s.`von` = '08:00:00' AND s.`titel` LIKE '%Startnummernausgabe%'
   AND NOT EXISTS (SELECT 1 FROM (SELECT `schicht_id`, `helfer_id` FROM `schicht_zuteilung`) z
                    WHERE z.`schicht_id` = s.`id` AND z.`helfer_id` = 10);
