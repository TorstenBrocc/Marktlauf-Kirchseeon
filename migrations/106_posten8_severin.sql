-- 106_posten8_severin.sql
-- Severin Lieske auf Posten 8 (Verkehr Ilching Mitte-Sued) — der letzte
-- unbesetzte Einsatzort. Severin hat sich am 19.09. angemeldet, nachdem die
-- Helferliste geschrieben war, und stand deshalb dort noch als "?".
--
-- Damit ist die Ilching-Kette komplett: P6 Nord, P7 Mitte-Nord, P8 Mitte-Sued,
-- P9 Sued, dazu V2 am Loeschteich.

SET NAMES utf8mb4;

INSERT INTO `schicht_zuteilung` (`schicht_id`, `helfer_id`)
SELECT s.`id`, h.`id` FROM `schichten` s JOIN `helfer` h
 WHERE s.`tag` = '2026-09-20' AND s.`postennummer` = 8
   AND h.`vorname` = 'Severin' AND h.`nachname` = 'Lieske'
   AND NOT EXISTS (SELECT 1 FROM (SELECT `schicht_id`, `helfer_id` FROM `schicht_zuteilung`) z
                    WHERE z.`schicht_id` = s.`id` AND z.`helfer_id` = h.`id`);
