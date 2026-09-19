-- 098_streckenposten_einteilung.sql
-- Die Streckenposten-Einteilung aus der Helferliste (Stand 19.09.2026) und die
-- Versorgungsstation Ilching.
--
-- Quelle ist die aktuelle Excel-Helferplanung, nicht mehr der Papierplan vom
-- 16.09. — sie ist neuer und traegt erstmals die Postennummern. Abweichungen
-- gegenueber dem Papierplan sind gewollt (z. B. Ilching jetzt anders besetzt,
-- und wer dort stand, uebernimmt dafuer die Posten 1+2).
--
-- Zwei Helfer stehen auf zwei Posten (Helfer #19 3+6, Helfer #24 4+9,
-- ein Helfer
--
-- Zuordnung ueber den Schicht-TITEL statt ueber IDs: die Postennummern und die
-- Helfer-IDs liegen im selben Zahlenbereich, eine Verwechslung waere hier
-- besonders teuer. Helfer werden ueber Vor-/Nachname aufgeloest; jede Zeile ist
-- guarded, laeuft also auch ein zweites Mal ohne Schaden.
--
-- NICHT enthalten:
--   * Posten 8 — in der Helferliste noch offen ("?").
--   * Posten 12 und 13 (Helfer #27 / Helfer #26): diese Nummern gibt es
--     im Streckenplan vom 18.09. nicht, der endet bei Posten 11. Solange
--     ungeklaert ist, ob der Plan oder die Liste vorgeht, wird hier nichts
--     geraten — die beiden bleiben unzugeteilt.
--   * Helfer #19 ist doppelt erfasst (#19 und #25, gleiche E-Mail). Die
--     Zuteilung geht an den aelteren Datensatz #19, der auch die
--     Selbstmeldungen traegt. Das Zusammenlegen der Dublette ist ein eigener
--     Schritt und passiert hier bewusst nicht.

-- HINWEIS: Diese Migration nannte urspruenglich Klarnamen. Das Repo ist
-- oeffentlich; die Zuordnung laeuft deshalb ueber Helfer-IDs. Der Datenstand
-- aendert sich dadurch nicht - die Migration ist laengst angewandt, und die
-- IDs treffen dieselben Datensaetze.

SET NAMES utf8mb4;

-- Posten 1 + 2:
INSERT INTO `schicht_zuteilung` (`schicht_id`, `helfer_id`)
SELECT s.`id`, h.`id` FROM `schichten` s JOIN `helfer` h
 WHERE s.`tag` = '2026-09-20' AND s.`titel` = 'Streckenposten 1 · Vollsperrung Nord oben'
   AND h.`id` = 8
   AND NOT EXISTS (SELECT 1 FROM (SELECT `schicht_id`, `helfer_id` FROM `schicht_zuteilung`) z
                    WHERE z.`schicht_id` = s.`id` AND z.`helfer_id` = h.`id`);

INSERT INTO `schicht_zuteilung` (`schicht_id`, `helfer_id`)
SELECT s.`id`, h.`id` FROM `schichten` s JOIN `helfer` h
 WHERE s.`tag` = '2026-09-20' AND s.`titel` = 'Streckenposten 2 · Vollsperrung Nord unten'
   AND h.`id` = 8
   AND NOT EXISTS (SELECT 1 FROM (SELECT `schicht_id`, `helfer_id` FROM `schicht_zuteilung`) z
                    WHERE z.`schicht_id` = s.`id` AND z.`helfer_id` = h.`id`);

-- Posten 3 + 6:
INSERT INTO `schicht_zuteilung` (`schicht_id`, `helfer_id`)
SELECT s.`id`, 19 FROM `schichten` s
 WHERE s.`tag` = '2026-09-20' AND s.`titel` = 'Streckenposten 3 · Vollsperrung Süd Friedhof'
   AND NOT EXISTS (SELECT 1 FROM (SELECT `schicht_id`, `helfer_id` FROM `schicht_zuteilung`) z
                    WHERE z.`schicht_id` = s.`id` AND z.`helfer_id` = 19);

INSERT INTO `schicht_zuteilung` (`schicht_id`, `helfer_id`)
SELECT s.`id`, 19 FROM `schichten` s
 WHERE s.`tag` = '2026-09-20' AND s.`titel` = 'Streckenposten 6 · Verkehr Ilching Süd'
   AND NOT EXISTS (SELECT 1 FROM (SELECT `schicht_id`, `helfer_id` FROM `schicht_zuteilung`) z
                    WHERE z.`schicht_id` = s.`id` AND z.`helfer_id` = 19);

-- Posten 4 + 9:
INSERT INTO `schicht_zuteilung` (`schicht_id`, `helfer_id`)
SELECT s.`id`, h.`id` FROM `schichten` s JOIN `helfer` h
 WHERE s.`tag` = '2026-09-20' AND s.`titel` = 'Streckenposten 4 · Vollsperrung Süd + Läuferlenkung'
   AND h.`id` = 24
   AND NOT EXISTS (SELECT 1 FROM (SELECT `schicht_id`, `helfer_id` FROM `schicht_zuteilung`) z
                    WHERE z.`schicht_id` = s.`id` AND z.`helfer_id` = h.`id`);

INSERT INTO `schicht_zuteilung` (`schicht_id`, `helfer_id`)
SELECT s.`id`, h.`id` FROM `schichten` s JOIN `helfer` h
 WHERE s.`tag` = '2026-09-20' AND s.`titel` = 'Streckenposten 9 · Verkehr Ilching Nord'
   AND h.`id` = 24
   AND NOT EXISTS (SELECT 1 FROM (SELECT `schicht_id`, `helfer_id` FROM `schicht_zuteilung`) z
                    WHERE z.`schicht_id` = s.`id` AND z.`helfer_id` = h.`id`);

-- Posten 5:
INSERT INTO `schicht_zuteilung` (`schicht_id`, `helfer_id`)
SELECT s.`id`, h.`id` FROM `schichten` s JOIN `helfer` h
 WHERE s.`tag` = '2026-09-20' AND s.`titel` = 'Streckenposten 5 · Trennung 5 km / 10 km'
   AND h.`id` = 28
   AND NOT EXISTS (SELECT 1 FROM (SELECT `schicht_id`, `helfer_id` FROM `schicht_zuteilung`) z
                    WHERE z.`schicht_id` = s.`id` AND z.`helfer_id` = h.`id`);

-- Posten 7:
INSERT INTO `schicht_zuteilung` (`schicht_id`, `helfer_id`)
SELECT s.`id`, h.`id` FROM `schichten` s JOIN `helfer` h
 WHERE s.`tag` = '2026-09-20' AND s.`titel` = 'Streckenposten 7 · Verkehr Ilching Mitte-Süd'
   AND h.`id` = 30
   AND NOT EXISTS (SELECT 1 FROM (SELECT `schicht_id`, `helfer_id` FROM `schicht_zuteilung`) z
                    WHERE z.`schicht_id` = s.`id` AND z.`helfer_id` = h.`id`);

-- Posten 10:
INSERT INTO `schicht_zuteilung` (`schicht_id`, `helfer_id`)
SELECT s.`id`, h.`id` FROM `schichten` s JOIN `helfer` h
 WHERE s.`tag` = '2026-09-20' AND s.`titel` = 'Streckenposten 10 · Wende 1 km'
   AND h.`id` = 31
   AND NOT EXISTS (SELECT 1 FROM (SELECT `schicht_id`, `helfer_id` FROM `schicht_zuteilung`) z
                    WHERE z.`schicht_id` = s.`id` AND z.`helfer_id` = h.`id`);

-- Posten 11:
INSERT INTO `schicht_zuteilung` (`schicht_id`, `helfer_id`)
SELECT s.`id`, h.`id` FROM `schichten` s JOIN `helfer` h
 WHERE s.`tag` = '2026-09-20' AND s.`titel` = 'Streckenposten 11 · Wende 500 m'
   AND h.`id` = 15
   AND NOT EXISTS (SELECT 1 FROM (SELECT `schicht_id`, `helfer_id` FROM `schicht_zuteilung`) z
                    WHERE z.`schicht_id` = s.`id` AND z.`helfer_id` = h.`id`);

-- Versorgung + Sanitaet Ilching:
INSERT INTO `schicht_zuteilung` (`schicht_id`, `helfer_id`)
SELECT s.`id`, h.`id` FROM `schichten` s JOIN `helfer` h
 WHERE s.`tag` = '2026-09-20' AND s.`titel` = 'Versorgung + Sanität Ilching (V2/S2)'
   AND h.`id` = 6
   AND NOT EXISTS (SELECT 1 FROM (SELECT `schicht_id`, `helfer_id` FROM `schicht_zuteilung`) z
                    WHERE z.`schicht_id` = s.`id` AND z.`helfer_id` = h.`id`);

INSERT INTO `schicht_zuteilung` (`schicht_id`, `helfer_id`)
SELECT s.`id`, h.`id` FROM `schichten` s JOIN `helfer` h
 WHERE s.`tag` = '2026-09-20' AND s.`titel` = 'Versorgung + Sanität Ilching (V2/S2)'
   AND h.`id` = 18
   AND NOT EXISTS (SELECT 1 FROM (SELECT `schicht_id`, `helfer_id` FROM `schicht_zuteilung`) z
                    WHERE z.`schicht_id` = s.`id` AND z.`helfer_id` = h.`id`);
