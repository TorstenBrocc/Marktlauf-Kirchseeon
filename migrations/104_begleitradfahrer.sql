-- 104_begleitradfahrer.sql
-- Die beiden Begleitradfahrer als eigene Einsatzorte, damit auch sie eine
-- persoenliche Seite mit Zeiten und Aufgabe bekommen.
--
-- Bisher gab es nur die Anmeldekategorie "Begleitradfahrer Laufstrecke"
-- (Bedarf 2, 09:00-12:30) — eine Zeile fuer zwei voellig verschiedene Aufgaben.
-- Die Helferliste vom 19.09. trennt sie: "Radfahrer Lauf vorne" (Helfer #29,
-- Helfer #7) und "Radfahrer Lauf hinten" (Helfer #16, Helfer #17).
-- Die Anmeldekategorie bleibt unangetastet (sie steht gesperrt im Formular);
-- hier kommen zwei interne Eintraege dazu, wie bei den Streckenposten.
--
-- KENNUNGEN R1/R2 statt Postennummern: die Radfahrer stehen nicht an einem
-- Punkt, sie fahren die Strecke ab — eine Postennummer waere irrefuehrend.
-- Aus demselben Grund bekommen sie KEINE Koordinate: ihr Einsatzort ist der
-- gesamte Kurs, ein Kartenpunkt wuerde einen festen Standort vortaeuschen.
--
-- ZEITEN, hergeleitet aus Blatt 'Parameter' der Durchlaufzeiten-Datei:
--   beide ab 10:45 vor Ort (Start 11:00 minus 15 Minuten Puffer)
--   R1 vorne bis 11:35  — die Spitze ist rechnerisch um 11:34 im Ziel
--                         (11:00 + 10,375 km x 3:18 min/km)
--   R2 hinten bis 12:30 — Zielschluss des 10-km-Laufs
--
-- R2 traegt eine Aufgabe, die sonst niemand hat: Die Vollsperrung darf erst
-- aufgehoben werden, wenn der letzte 10-km-Laeufer den Freigabepunkt passiert
-- hat (48.074971, 11.857462, km 9,37 — Festlegung TT 18.09. anstelle der
-- 'Bruecke' aus VAO-Auflage 4). Wer hinter dem Feld faehrt, sieht das als
-- Einziger. Das Blatt 'Offene Punkte' der Excel fuehrt genau das als offen:
-- "Vor- und Nachlaeufer ... niemand benannt - und der Nachlaeufer meldet den
-- Freigabe-Schluesselpunkt." Mit dieser Migration ist es benannt.

-- HINWEIS: Diese Migration nannte urspruenglich Klarnamen. Das Repo ist
-- oeffentlich; die Zuordnung laeuft deshalb ueber Helfer-IDs. Der Datenstand
-- aendert sich dadurch nicht - die Migration ist laengst angewandt, und die
-- IDs treffen dieselben Datensaetze.

SET NAMES utf8mb4;

INSERT INTO `schichten`
  (`titel`, `kennung`, `beschreibung`, `ort`, `tag`, `von`, `bis`, `zeitfenster`, `bedarf`, `in_anmeldung`, `sortierung`)
SELECT 'Begleitradfahrer vorne (R1)', 'R1',
       'Mit dem Rad vor dem Laeuferfeld. Aufgabe: dem Spitzenfeld den Weg freihalten und die Strecke vorweg pruefen — Fahrzeuge, Fussgaenger, Hindernisse. Faehrt den 10-km-Kurs ab; die Spitze ist rechnerisch um 11:34 Uhr im Ziel. Standortmeldung immer mit Lauf und naechster Marke, z. B. „Person auf dem 10 km kurz nach km 6".',
       'gesamte Laufstrecke', '2026-09-20', '10:45:00', '11:35:00', NULL, 2, 0, 655
  FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id` FROM `schichten`
        WHERE `tag` = '2026-09-20' AND `kennung` = 'R1') AS vorhanden);

INSERT INTO `schichten`
  (`titel`, `kennung`, `beschreibung`, `ort`, `tag`, `von`, `bis`, `zeitfenster`, `bedarf`, `in_anmeldung`, `sortierung`)
SELECT 'Begleitradfahrer hinten (R2)', 'R2',
       'Mit dem Rad hinter dem letzten Laeufer. Zwei Aufgaben: niemanden auf der Strecke zuruecklassen — und die FREIGABE MELDEN. Die Vollsperrung wird erst aufgehoben, wenn der letzte 10-km-Laeufer den Freigabepunkt bei 48.074971, 11.857462 (km 9,37) passiert hat; rechnerisch 12:21 Uhr. Sobald das der Fall ist, sofort an die Streckenleitung melden — erst dann geht die Sperrung auf. Standortmeldung immer mit Lauf und naechster Marke.',
       'gesamte Laufstrecke', '2026-09-20', '10:45:00', '12:30:00', NULL, 2, 0, 656
  FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM (SELECT `id` FROM `schichten`
        WHERE `tag` = '2026-09-20' AND `kennung` = 'R2') AS vorhanden);

-- Einteilung nach der Helferliste vom 19.09.
INSERT INTO `schicht_zuteilung` (`schicht_id`, `helfer_id`)
SELECT s.`id`, h.`id` FROM `schichten` s JOIN `helfer` h
 WHERE s.`tag` = '2026-09-20' AND s.`kennung` = 'R1'
   AND h.`id` = 29
   AND NOT EXISTS (SELECT 1 FROM (SELECT `schicht_id`, `helfer_id` FROM `schicht_zuteilung`) z
                    WHERE z.`schicht_id` = s.`id` AND z.`helfer_id` = h.`id`);

INSERT INTO `schicht_zuteilung` (`schicht_id`, `helfer_id`)
SELECT s.`id`, h.`id` FROM `schichten` s JOIN `helfer` h
 WHERE s.`tag` = '2026-09-20' AND s.`kennung` = 'R1'
   AND h.`id` = 7
   AND NOT EXISTS (SELECT 1 FROM (SELECT `schicht_id`, `helfer_id` FROM `schicht_zuteilung`) z
                    WHERE z.`schicht_id` = s.`id` AND z.`helfer_id` = h.`id`);

INSERT INTO `schicht_zuteilung` (`schicht_id`, `helfer_id`)
SELECT s.`id`, h.`id` FROM `schichten` s JOIN `helfer` h
 WHERE s.`tag` = '2026-09-20' AND s.`kennung` = 'R2'
   AND h.`id` = 16
   AND NOT EXISTS (SELECT 1 FROM (SELECT `schicht_id`, `helfer_id` FROM `schicht_zuteilung`) z
                    WHERE z.`schicht_id` = s.`id` AND z.`helfer_id` = h.`id`);

INSERT INTO `schicht_zuteilung` (`schicht_id`, `helfer_id`)
SELECT s.`id`, h.`id` FROM `schichten` s JOIN `helfer` h
 WHERE s.`tag` = '2026-09-20' AND s.`kennung` = 'R2'
   AND h.`id` = 17
   AND NOT EXISTS (SELECT 1 FROM (SELECT `schicht_id`, `helfer_id` FROM `schicht_zuteilung`) z
                    WHERE z.`schicht_id` = s.`id` AND z.`helfer_id` = h.`id`);
