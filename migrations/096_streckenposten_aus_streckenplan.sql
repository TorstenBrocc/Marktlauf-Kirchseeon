-- 096_streckenposten_aus_streckenplan.sql
-- Die Streckenposten aus dem Streckenplan 2026 (Luftbild-PDF, Stand 18.09.2026)
-- als einzelne Schichten im Einsatzplan.
--
-- Warum einzeln: Bisher gibt es im System nur eine Sammel-Schicht
-- "Streckenposten Versorgungsstationen Laufstrecke" (Bedarf 1) -- eine
-- Anmeldekategorie. Der Streckenplan kennt dagegen elf konkrete Standorte mit
-- unterschiedlicher Aufgabe und Zeitlage. Wer wo steht, ist am Renntag die
-- entscheidende Information; sie gehoert an den Posten, nicht in eine Sammelzeile.
-- Erst so kann auf dem persoenlichen Helfer-PDF "Streckenposten 7 - Verkehr
-- Ilching Mitte-Sued" stehen statt nur "Streckenposten".
--
-- in_anmeldung = 0 (nur intern): Diese Posten sind Einsatzplanung, keine
-- Anmeldeoptionen. Das oeffentliche Anmeldeformular bleibt unveraendert.
--
-- Zeiten -- nur was der Plan belegt:
--   * Posten 1-4 tragen die Vollsperrung Bucher Strasse / Eglhartinger Strasse,
--     11:00-12:30 Uhr (VAO 2026-085). Aufhebung erst, wenn der letzte
--     10-km-Laeufer den markierten Punkt passiert hat (rechnerisch 12:21 Uhr).
--   * Posten 10/11 sind die Wendepunkte der Kurzstrecken; deren Startzeiten
--     stehen im Plankopf (500 m ab 10:00, 1 km ab 10:30).
--   * Fuer die Posten 5-9 nennt der Plan keine Zeiten -- sie bekommen das
--     Freitext-Zeitfenster "waehrend des Laufs" statt einer erfundenen Uhrzeit.
--
-- Die Versorgungsstation Ilching (V2/S2) fehlt bisher ganz und kommt mit dazu.
-- Die Hauptstation Start/Ziel (V1/S1) wird NICHT angelegt -- dafuer gibt es
-- bereits "Betreuung / Aufbau Versorgungsstation Start/Ziel".
--
-- `sortierung` (Migration 095) haelt die Postenkette 1..11 als Block zusammen.
-- Im Board laesst sich die Reihenfolge per Drag aendern.
--
-- Alle INSERTs guarded nach dem Muster aus 094 (FROM DUAL + NOT EXISTS ueber
-- eine abgeleitete Tabelle, umgeht MySQL-Fehler 1093): die Migration kann
-- gefahrlos zweimal laufen und legt nichts doppelt an.

SET NAMES utf8mb4;

INSERT INTO `schichten` (`titel`, `beschreibung`, `ort`, `tag`, `von`, `bis`, `zeitfenster`, `bedarf`, `in_anmeldung`, `sortierung`)
SELECT 'Streckenposten 1 · Vollsperrung Nord oben', 'Warnweste tragen. Vollsperrung Bucher Straße / Eglhartinger Straße 11:00–12:30 Uhr (VAO 2026-085). Aufhebung erst, wenn der letzte 10-km-Läufer den markierten Punkt passiert hat — rechnerisch 12:21 Uhr. Standortmeldung immer mit Lauf und nächster Marke, z. B. „Person auf dem 10 km kurz nach km 6".', NULL, '2026-09-20', '11:00:00', '12:30:00', NULL, 1, 0, 660
  FROM DUAL
 WHERE NOT EXISTS (
       SELECT 1 FROM (
           SELECT `id` FROM `schichten`
            WHERE `tag` = '2026-09-20' AND `titel` = 'Streckenposten 1 · Vollsperrung Nord oben'
       ) AS vorhanden
 );

INSERT INTO `schichten` (`titel`, `beschreibung`, `ort`, `tag`, `von`, `bis`, `zeitfenster`, `bedarf`, `in_anmeldung`, `sortierung`)
SELECT 'Streckenposten 2 · Vollsperrung Nord unten', 'Warnweste tragen. Vollsperrung Bucher Straße / Eglhartinger Straße 11:00–12:30 Uhr (VAO 2026-085). Aufhebung erst, wenn der letzte 10-km-Läufer den markierten Punkt passiert hat — rechnerisch 12:21 Uhr. Standortmeldung immer mit Lauf und nächster Marke, z. B. „Person auf dem 10 km kurz nach km 6".', NULL, '2026-09-20', '11:00:00', '12:30:00', NULL, 1, 0, 661
  FROM DUAL
 WHERE NOT EXISTS (
       SELECT 1 FROM (
           SELECT `id` FROM `schichten`
            WHERE `tag` = '2026-09-20' AND `titel` = 'Streckenposten 2 · Vollsperrung Nord unten'
       ) AS vorhanden
 );

INSERT INTO `schichten` (`titel`, `beschreibung`, `ort`, `tag`, `von`, `bis`, `zeitfenster`, `bedarf`, `in_anmeldung`, `sortierung`)
SELECT 'Streckenposten 3 · Vollsperrung Süd Friedhof', 'Warnweste tragen. Vollsperrung Bucher Straße / Eglhartinger Straße 11:00–12:30 Uhr (VAO 2026-085). Aufhebung erst, wenn der letzte 10-km-Läufer den markierten Punkt passiert hat — rechnerisch 12:21 Uhr. Standortmeldung immer mit Lauf und nächster Marke, z. B. „Person auf dem 10 km kurz nach km 6".', 'Buch, Friedhof', '2026-09-20', '11:00:00', '12:30:00', NULL, 1, 0, 662
  FROM DUAL
 WHERE NOT EXISTS (
       SELECT 1 FROM (
           SELECT `id` FROM `schichten`
            WHERE `tag` = '2026-09-20' AND `titel` = 'Streckenposten 3 · Vollsperrung Süd Friedhof'
       ) AS vorhanden
 );

INSERT INTO `schichten` (`titel`, `beschreibung`, `ort`, `tag`, `von`, `bis`, `zeitfenster`, `bedarf`, `in_anmeldung`, `sortierung`)
SELECT 'Streckenposten 4 · Vollsperrung Süd + Läuferlenkung', 'Warnweste tragen. Vollsperrung 11:00–12:30 Uhr (VAO 2026-085) UND Läuferlenkung an dieser Stelle. Aufhebung erst, wenn der letzte 10-km-Läufer den markierten Punkt passiert hat — rechnerisch 12:21 Uhr. Standortmeldung immer mit Lauf und nächster Marke, z. B. „Person auf dem 10 km kurz nach km 6".', 'Buch', '2026-09-20', '11:00:00', '12:30:00', NULL, 1, 0, 663
  FROM DUAL
 WHERE NOT EXISTS (
       SELECT 1 FROM (
           SELECT `id` FROM `schichten`
            WHERE `tag` = '2026-09-20' AND `titel` = 'Streckenposten 4 · Vollsperrung Süd + Läuferlenkung'
       ) AS vorhanden
 );

INSERT INTO `schichten` (`titel`, `beschreibung`, `ort`, `tag`, `von`, `bis`, `zeitfenster`, `bedarf`, `in_anmeldung`, `sortierung`)
SELECT 'Streckenposten 5 · Trennung 5 km / 10 km', 'Warnweste tragen. Hier trennen sich 5-km- und 10-km-Kurs — Läufer zuverlässig auf die richtige Strecke schicken. Standortmeldung immer mit Lauf und nächster Marke, z. B. „Person auf dem 10 km kurz nach km 6".', NULL, '2026-09-20', NULL, NULL, 'während des Laufs', 1, 0, 664
  FROM DUAL
 WHERE NOT EXISTS (
       SELECT 1 FROM (
           SELECT `id` FROM `schichten`
            WHERE `tag` = '2026-09-20' AND `titel` = 'Streckenposten 5 · Trennung 5 km / 10 km'
       ) AS vorhanden
 );

INSERT INTO `schichten` (`titel`, `beschreibung`, `ort`, `tag`, `von`, `bis`, `zeitfenster`, `bedarf`, `in_anmeldung`, `sortierung`)
SELECT 'Streckenposten 6 · Verkehr Ilching Süd', 'Warnweste tragen. Verkehrssicherung am südlichen Ortseingang Ilching. Standortmeldung immer mit Lauf und nächster Marke, z. B. „Person auf dem 10 km kurz nach km 6".', 'Ilching', '2026-09-20', NULL, NULL, 'während des Laufs', 1, 0, 665
  FROM DUAL
 WHERE NOT EXISTS (
       SELECT 1 FROM (
           SELECT `id` FROM `schichten`
            WHERE `tag` = '2026-09-20' AND `titel` = 'Streckenposten 6 · Verkehr Ilching Süd'
       ) AS vorhanden
 );

INSERT INTO `schichten` (`titel`, `beschreibung`, `ort`, `tag`, `von`, `bis`, `zeitfenster`, `bedarf`, `in_anmeldung`, `sortierung`)
SELECT 'Streckenposten 7 · Verkehr Ilching Mitte-Süd', 'Warnweste tragen. Verkehrssicherung Ortsmitte Ilching (Süd), nahe der Versorgungsstation am Löschteich West. Standortmeldung immer mit Lauf und nächster Marke, z. B. „Person auf dem 10 km kurz nach km 6".', 'Ilching', '2026-09-20', NULL, NULL, 'während des Laufs', 1, 0, 666
  FROM DUAL
 WHERE NOT EXISTS (
       SELECT 1 FROM (
           SELECT `id` FROM `schichten`
            WHERE `tag` = '2026-09-20' AND `titel` = 'Streckenposten 7 · Verkehr Ilching Mitte-Süd'
       ) AS vorhanden
 );

INSERT INTO `schichten` (`titel`, `beschreibung`, `ort`, `tag`, `von`, `bis`, `zeitfenster`, `bedarf`, `in_anmeldung`, `sortierung`)
SELECT 'Streckenposten 8 · Verkehr Ilching Mitte-Nord', 'Warnweste tragen. Verkehrssicherung Ortsmitte Ilching (Nord). Standortmeldung immer mit Lauf und nächster Marke, z. B. „Person auf dem 10 km kurz nach km 6".', 'Ilching', '2026-09-20', NULL, NULL, 'während des Laufs', 1, 0, 667
  FROM DUAL
 WHERE NOT EXISTS (
       SELECT 1 FROM (
           SELECT `id` FROM `schichten`
            WHERE `tag` = '2026-09-20' AND `titel` = 'Streckenposten 8 · Verkehr Ilching Mitte-Nord'
       ) AS vorhanden
 );

INSERT INTO `schichten` (`titel`, `beschreibung`, `ort`, `tag`, `von`, `bis`, `zeitfenster`, `bedarf`, `in_anmeldung`, `sortierung`)
SELECT 'Streckenposten 9 · Verkehr Ilching Nord', 'Warnweste tragen. Verkehrssicherung am nördlichen Ortsausgang Ilching. Standortmeldung immer mit Lauf und nächster Marke, z. B. „Person auf dem 10 km kurz nach km 6".', 'Ilching', '2026-09-20', NULL, NULL, 'während des Laufs', 1, 0, 668
  FROM DUAL
 WHERE NOT EXISTS (
       SELECT 1 FROM (
           SELECT `id` FROM `schichten`
            WHERE `tag` = '2026-09-20' AND `titel` = 'Streckenposten 9 · Verkehr Ilching Nord'
       ) AS vorhanden
 );

INSERT INTO `schichten` (`titel`, `beschreibung`, `ort`, `tag`, `von`, `bis`, `zeitfenster`, `bedarf`, `in_anmeldung`, `sortierung`)
SELECT 'Streckenposten 10 · Wende 1 km', 'Warnweste tragen. Wendepunkt des 1-km-Kurses (Schülerlauf, Start 10:30). Läufer sicher wenden lassen. Standortmeldung immer mit Lauf und nächster Marke, z. B. „Person auf dem 10 km kurz nach km 6".', NULL, '2026-09-20', '10:30:00', NULL, NULL, 1, 0, 669
  FROM DUAL
 WHERE NOT EXISTS (
       SELECT 1 FROM (
           SELECT `id` FROM `schichten`
            WHERE `tag` = '2026-09-20' AND `titel` = 'Streckenposten 10 · Wende 1 km'
       ) AS vorhanden
 );

INSERT INTO `schichten` (`titel`, `beschreibung`, `ort`, `tag`, `von`, `bis`, `zeitfenster`, `bedarf`, `in_anmeldung`, `sortierung`)
SELECT 'Streckenposten 11 · Wende 500 m', 'Warnweste tragen. Wendepunkt des 500-m-Kurses (Bambini, Start 10:00). Kinder sicher wenden lassen. Standortmeldung immer mit Lauf und nächster Marke, z. B. „Person auf dem 10 km kurz nach km 6".', NULL, '2026-09-20', '10:00:00', NULL, NULL, 1, 0, 670
  FROM DUAL
 WHERE NOT EXISTS (
       SELECT 1 FROM (
           SELECT `id` FROM `schichten`
            WHERE `tag` = '2026-09-20' AND `titel` = 'Streckenposten 11 · Wende 500 m'
       ) AS vorhanden
 );

INSERT INTO `schichten` (`titel`, `beschreibung`, `ort`, `tag`, `von`, `bis`, `zeitfenster`, `bedarf`, `in_anmeldung`, `sortierung`)
SELECT 'Versorgung + Sanität Ilching (V2/S2)', 'Verpflegungs- und Sanitätsstation Ilching am Löschteich West. Keine Warnwestenpflicht. Standortmeldung immer mit Lauf und nächster Marke, z. B. „Person auf dem 10 km kurz nach km 6".', 'Ilching, Löschteich West', '2026-09-20', NULL, NULL, 'während des Laufs', 2, 0, 671
  FROM DUAL
 WHERE NOT EXISTS (
       SELECT 1 FROM (
           SELECT `id` FROM `schichten`
            WHERE `tag` = '2026-09-20' AND `titel` = 'Versorgung + Sanität Ilching (V2/S2)'
       ) AS vorhanden
 );
