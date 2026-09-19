-- 098_streckenposten_einteilung.sql
-- Die Streckenposten-Einteilung aus der Helferliste (Stand 19.09.2026) und die
-- Versorgungsstation Ilching.
--
-- Quelle ist die aktuelle Excel-Helferplanung, nicht mehr der Papierplan vom
-- 16.09. — sie ist neuer und traegt erstmals die Postennummern. Abweichungen
-- gegenueber dem Papierplan sind gewollt (z. B. Ilching jetzt Bayer/Reichmeyer
-- statt Riesmeyer-Lorenz, die dafuer die Posten 1+2 uebernimmt).
--
-- Zwei Helfer stehen auf zwei Posten (Betzl Anke 3+6, Betzl Luisa 4+9,
-- Riesmeyer 1+2) — schicht_zuteilung ist M:N, das traegt das ohne Weiteres.
--
-- Zuordnung ueber den Schicht-TITEL statt ueber IDs: die Postennummern und die
-- Helfer-IDs liegen im selben Zahlenbereich, eine Verwechslung waere hier
-- besonders teuer. Helfer werden ueber Vor-/Nachname aufgeloest; jede Zeile ist
-- guarded, laeuft also auch ein zweites Mal ohne Schaden.
--
-- NICHT enthalten:
--   * Posten 8 — in der Helferliste noch offen ("?").
--   * Posten 12 und 13 (Lieske Marco / Lieske Christine): diese Nummern gibt es
--     im Streckenplan vom 18.09. nicht, der endet bei Posten 11. Solange
--     ungeklaert ist, ob der Plan oder die Liste vorgeht, wird hier nichts
--     geraten — die beiden bleiben unzugeteilt.
--   * Betzl Anke ist doppelt erfasst (#19 und #25, gleiche E-Mail). Die
--     Zuteilung geht an den aelteren Datensatz #19, der auch die
--     Selbstmeldungen traegt. Das Zusammenlegen der Dublette ist ein eigener
--     Schritt und passiert hier bewusst nicht.

SET NAMES utf8mb4;

-- Posten 1 + 2: Riesmeyer(-Lorenz) Claudia
INSERT INTO `schicht_zuteilung` (`schicht_id`, `helfer_id`)
SELECT s.`id`, h.`id` FROM `schichten` s JOIN `helfer` h
 WHERE s.`tag` = '2026-09-20' AND s.`titel` = 'Streckenposten 1 · Vollsperrung Nord oben'
   AND h.`vorname` = 'Claudia' AND h.`nachname` = 'Riesmeyer'
   AND NOT EXISTS (SELECT 1 FROM (SELECT `schicht_id`, `helfer_id` FROM `schicht_zuteilung`) z
                    WHERE z.`schicht_id` = s.`id` AND z.`helfer_id` = h.`id`);

INSERT INTO `schicht_zuteilung` (`schicht_id`, `helfer_id`)
SELECT s.`id`, h.`id` FROM `schichten` s JOIN `helfer` h
 WHERE s.`tag` = '2026-09-20' AND s.`titel` = 'Streckenposten 2 · Vollsperrung Nord unten'
   AND h.`vorname` = 'Claudia' AND h.`nachname` = 'Riesmeyer'
   AND NOT EXISTS (SELECT 1 FROM (SELECT `schicht_id`, `helfer_id` FROM `schicht_zuteilung`) z
                    WHERE z.`schicht_id` = s.`id` AND z.`helfer_id` = h.`id`);

-- Posten 3 + 6: Betzl Anke (Datensatz #19)
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

-- Posten 4 + 9: Betzl Luisa
INSERT INTO `schicht_zuteilung` (`schicht_id`, `helfer_id`)
SELECT s.`id`, h.`id` FROM `schichten` s JOIN `helfer` h
 WHERE s.`tag` = '2026-09-20' AND s.`titel` = 'Streckenposten 4 · Vollsperrung Süd + Läuferlenkung'
   AND h.`vorname` = 'Luisa' AND h.`nachname` = 'Betzl'
   AND NOT EXISTS (SELECT 1 FROM (SELECT `schicht_id`, `helfer_id` FROM `schicht_zuteilung`) z
                    WHERE z.`schicht_id` = s.`id` AND z.`helfer_id` = h.`id`);

INSERT INTO `schicht_zuteilung` (`schicht_id`, `helfer_id`)
SELECT s.`id`, h.`id` FROM `schichten` s JOIN `helfer` h
 WHERE s.`tag` = '2026-09-20' AND s.`titel` = 'Streckenposten 9 · Verkehr Ilching Nord'
   AND h.`vorname` = 'Luisa' AND h.`nachname` = 'Betzl'
   AND NOT EXISTS (SELECT 1 FROM (SELECT `schicht_id`, `helfer_id` FROM `schicht_zuteilung`) z
                    WHERE z.`schicht_id` = s.`id` AND z.`helfer_id` = h.`id`);

-- Posten 5: Kilian Sandra
INSERT INTO `schicht_zuteilung` (`schicht_id`, `helfer_id`)
SELECT s.`id`, h.`id` FROM `schichten` s JOIN `helfer` h
 WHERE s.`tag` = '2026-09-20' AND s.`titel` = 'Streckenposten 5 · Trennung 5 km / 10 km'
   AND h.`vorname` = 'Sandra' AND h.`nachname` = 'Kilian'
   AND NOT EXISTS (SELECT 1 FROM (SELECT `schicht_id`, `helfer_id` FROM `schicht_zuteilung`) z
                    WHERE z.`schicht_id` = s.`id` AND z.`helfer_id` = h.`id`);

-- Posten 7: Richter Daniel
INSERT INTO `schicht_zuteilung` (`schicht_id`, `helfer_id`)
SELECT s.`id`, h.`id` FROM `schichten` s JOIN `helfer` h
 WHERE s.`tag` = '2026-09-20' AND s.`titel` = 'Streckenposten 7 · Verkehr Ilching Mitte-Süd'
   AND h.`vorname` = 'Daniel' AND h.`nachname` = 'Richter'
   AND NOT EXISTS (SELECT 1 FROM (SELECT `schicht_id`, `helfer_id` FROM `schicht_zuteilung`) z
                    WHERE z.`schicht_id` = s.`id` AND z.`helfer_id` = h.`id`);

-- Posten 10: Stiglbauer Jana
INSERT INTO `schicht_zuteilung` (`schicht_id`, `helfer_id`)
SELECT s.`id`, h.`id` FROM `schichten` s JOIN `helfer` h
 WHERE s.`tag` = '2026-09-20' AND s.`titel` = 'Streckenposten 10 · Wende 1 km'
   AND h.`vorname` = 'Jana' AND h.`nachname` = 'Stiglbauer'
   AND NOT EXISTS (SELECT 1 FROM (SELECT `schicht_id`, `helfer_id` FROM `schicht_zuteilung`) z
                    WHERE z.`schicht_id` = s.`id` AND z.`helfer_id` = h.`id`);

-- Posten 11: King Christiane
INSERT INTO `schicht_zuteilung` (`schicht_id`, `helfer_id`)
SELECT s.`id`, h.`id` FROM `schichten` s JOIN `helfer` h
 WHERE s.`tag` = '2026-09-20' AND s.`titel` = 'Streckenposten 11 · Wende 500 m'
   AND h.`vorname` = 'Christiane' AND h.`nachname` = 'King'
   AND NOT EXISTS (SELECT 1 FROM (SELECT `schicht_id`, `helfer_id` FROM `schicht_zuteilung`) z
                    WHERE z.`schicht_id` = s.`id` AND z.`helfer_id` = h.`id`);

-- Versorgung + Sanität Ilching: Bayer Sita und Reichmeyer Claudia
INSERT INTO `schicht_zuteilung` (`schicht_id`, `helfer_id`)
SELECT s.`id`, h.`id` FROM `schichten` s JOIN `helfer` h
 WHERE s.`tag` = '2026-09-20' AND s.`titel` = 'Versorgung + Sanität Ilching (V2/S2)'
   AND h.`vorname` = 'Sita' AND h.`nachname` = 'Bayer'
   AND NOT EXISTS (SELECT 1 FROM (SELECT `schicht_id`, `helfer_id` FROM `schicht_zuteilung`) z
                    WHERE z.`schicht_id` = s.`id` AND z.`helfer_id` = h.`id`);

INSERT INTO `schicht_zuteilung` (`schicht_id`, `helfer_id`)
SELECT s.`id`, h.`id` FROM `schichten` s JOIN `helfer` h
 WHERE s.`tag` = '2026-09-20' AND s.`titel` = 'Versorgung + Sanität Ilching (V2/S2)'
   AND h.`vorname` = 'Claudia' AND h.`nachname` = 'Reichmeyer'
   AND NOT EXISTS (SELECT 1 FROM (SELECT `schicht_id`, `helfer_id` FROM `schicht_zuteilung`) z
                    WHERE z.`schicht_id` = s.`id` AND z.`helfer_id` = h.`id`);
