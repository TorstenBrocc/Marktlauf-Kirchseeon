-- 108_fenster_laufgebundene_posten.sql
-- Korrektur der Einsatzfenster fuer die vier laufgebundenen Posten.
--
-- FEHLER IN 101: Dort wurde fuer JEDEN Posten ueber ALLE Passagen aller fuenf
-- Laeufe gerechnet. Das ist richtig, wo die Aufgabe am Laeuferstrom haengt
-- (Verkehrskoordination, Vollsperrung, Laeuferlenkung) — dort muss jemand
-- stehen, solange ueberhaupt gelaufen wird.
--
-- Bei Wendepunkten und Wegweisern ist es falsch. Ein Wendepunkt-Markierer fuer
-- die Bambini hat nichts mehr zu tun, sobald der 500-m-Lauf durch ist; dass
-- spaeter 5- und 10-km-Laeufer an derselben Stelle vorbeikommen, aendert daran
-- nichts — die laufen dort geradeaus weiter und brauchen weder Wende noch
-- Wegweisung. Die Excel trennt das selbst, in der Spalte "Art":
--
--   Wendepunkt-Markierung / Wegweiser  -> gilt genau EINEM Lauf   (P1-P4)
--   Laeuferlenkung / Verkehr / Sperre  -> gilt allen Passagen     (P5-P13)
--
-- Die Folge war betraechtlich: P1 stand mit 09:46-12:28 in der Liste, obwohl
-- der 500-m-Lauf um 10:15 Zielschluss hat. Und die Einteilung wirkte
-- unmoeglich, wo sie es nicht ist: Der Posten, der P3 und P6 haelt, kann beides
-- nacheinander schaffen (1 km Weg, zwoelf Minuten Zeit) — mit dem alten Fenster
-- sah es nach zwei gleichzeitigen Einsaetzen aus.
--
-- Neu gerechnet, je nur mit dem zugehoerigen Lauf:
--   P1 Wende 500 m    500-m-Lauf, km 0,25  ->  09:46 - 10:07
--   P2 Wende 1 km     1-km-Lauf,   km 0,50  ->  10:17 - 10:40
--   P3 Wegweiser 2 km 2-km-Lauf,   km 1,12  ->  10:20 - 10:44
--   P4 2 km Wende     2-km-Lauf,   km 0,77  ->  10:18 - 10:40  (unveraendert)
--
-- Formel wie gehabt: erster Laeufer minus 15 Minuten Puffer bis letzter Laeufer
-- im Zeitlimit. In die Beschreibung kommt dazu, welchem Lauf der Posten dient —
-- damit am Renntag niemand raetselt, warum er frueher gehen darf als der Posten
-- 200 Meter weiter.

SET NAMES utf8mb4;

UPDATE `schichten` SET `von` = '09:46:00', `bis` = '10:07:00',
       `beschreibung` = CONCAT(`beschreibung`, ' Dieser Posten gilt nur dem 500-m-Lauf der Bambini (Start 10:00, Zielschluss 10:15) — danach ist er frei; die späteren Läufe passieren die Stelle, ohne zu wenden.')
 WHERE `tag` = '2026-09-20' AND `postennummer` = 1 AND `beschreibung` NOT LIKE '%gilt nur dem%';

UPDATE `schichten` SET `von` = '10:17:00', `bis` = '10:40:00',
       `beschreibung` = CONCAT(`beschreibung`, ' Dieser Posten gilt nur dem 1-km-Schülerlauf (Start 10:30) — danach ist er frei; die späteren Läufe passieren die Stelle, ohne zu wenden.')
 WHERE `tag` = '2026-09-20' AND `postennummer` = 2 AND `beschreibung` NOT LIKE '%gilt nur dem%';

UPDATE `schichten` SET `von` = '10:20:00', `bis` = '10:44:00',
       `beschreibung` = CONCAT(`beschreibung`, ' Dieser Posten gilt nur dem 2-km-Schülerlauf (Start 10:30) — danach ist er frei; die späteren Läufe brauchen an dieser Stelle keine Wegweisung.')
 WHERE `tag` = '2026-09-20' AND `postennummer` = 3 AND `beschreibung` NOT LIKE '%gilt nur dem%';

UPDATE `schichten` SET `von` = '10:18:00', `bis` = '10:40:00',
       `beschreibung` = CONCAT(`beschreibung`, ' Dieser Posten gilt nur dem 2-km-Schülerlauf (Start 10:30) — er liegt 200 m abseits des 10-km-Kurses und wird danach nicht mehr gebraucht.')
 WHERE `tag` = '2026-09-20' AND `postennummer` = 4 AND `beschreibung` NOT LIKE '%gilt nur dem%';
