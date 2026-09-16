-- 094_helfer_anmeldung_2026_restbedarf.sql
-- Datenstand der Helfer-Anmeldung vier Tage vor dem Lauf 2026.
--
-- Der Bedarf ist bis auf drei Punkte gedeckt. Statt die erledigten Aufgaben zu
-- verstecken, stehen sie ab jetzt gesperrt (Zustand 2 aus Migration 093) in der
-- Maske: der Helfer sieht, was es gab und dass dort nichts mehr noetig ist.
--
-- Offen bleiben am Sonntag: Streckenposten, Abbau und die freie Verfuegbarkeit.
-- Neu dazu kommen die Begleitradfahrer -- sie gehoeren in den Einsatzplan,
-- werden aber nicht oeffentlich gesucht, also direkt gesperrt.
--
-- Alle Schritte sind idempotent bzw. guarded: Schritt 1 fasst nur an, was gerade
-- buchbar ist; der INSERT laeuft ueber NOT EXISTS; Schritt 3 oeffnet gezielt.
-- Getroffen wird ueber Tag + Titel, weil die schicht_id-Vergabe hier nicht
-- bekannt ist (die Titel stammen aus dem Live-Formular).

SET NAMES utf8mb4;

-- 1) Alles, was aktuell buchbar ist, auf "sichtbar, aber gesperrt".
--    Schichten, die schon "nur intern" (0) sind, bleiben unberuehrt.
UPDATE `schichten`
   SET `in_anmeldung` = 2
 WHERE `in_anmeldung` = 1;

-- 2) Begleitradfahrer anlegen -- sichtbar, aber gesperrt.
INSERT INTO `schichten` (`titel`, `beschreibung`, `ort`, `tag`, `von`, `bis`, `bedarf`, `zeitfenster`, `in_anmeldung`)
SELECT 'Begleitradfahrer Laufstrecke', NULL, NULL, '2026-09-20', '09:00:00', '12:30:00', 2, NULL, 2
  FROM DUAL
 WHERE NOT EXISTS (
       SELECT 1 FROM (
           SELECT `id` FROM `schichten`
            WHERE `tag` = '2026-09-20' AND `titel` = 'Begleitradfahrer Laufstrecke'
       ) AS vorhanden
 );

-- 3) Die drei Punkte wieder freigeben, fuer die noch Helfer gesucht werden.
UPDATE `schichten`
   SET `in_anmeldung` = 1
 WHERE `tag` = '2026-09-20'
   AND `in_anmeldung` = 2
   AND (
          `titel` LIKE 'Streckenposten%'
       OR `titel` LIKE 'Abbau%'
       OR (`von` IS NULL AND `titel` LIKE 'Ganzer Tag%')
   );
