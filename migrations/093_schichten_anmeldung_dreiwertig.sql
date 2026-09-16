-- 093_schichten_anmeldung_dreiwertig.sql
-- Helfer-Anmeldung: dritter Sichtbarkeits-Zustand "gesperrt anzeigen".
--
-- Bisher war `in_anmeldung` binaer: 1 = im oeffentlichen Formular, 0 = nur intern.
-- Damit liess sich eine Aufgabe nur komplett verstecken, sobald kein Bedarf mehr
-- besteht -- der Helfer sieht dann nicht, dass es den Termin gab. Ab jetzt:
--
--   0 = nur intern      -> taucht im Formular gar nicht auf
--   1 = in Anmeldung    -> sichtbar UND anklickbar
--   2 = gesperrt        -> sichtbar, aber ausgegraut und nicht buchbar
--
-- Kein Typwechsel: TINYINT fasst die 2 bereits, die Spaltenbreite (1) ist nur
-- eine Anzeige-Hilfe. Es aendert sich ausschliesslich die Semantik -- deshalb
-- haelt der COMMENT sie direkt an der Spalte fest. Bestandsdaten (0/1) behalten
-- ihre Bedeutung unveraendert; den Datenstand setzt Migration 094.

SET NAMES utf8mb4;

ALTER TABLE `schichten`
    MODIFY COLUMN `in_anmeldung` TINYINT(1) NOT NULL DEFAULT 1
    COMMENT '0 = nur intern, 1 = in Anmeldung buchbar, 2 = in Anmeldung sichtbar aber gesperrt';
