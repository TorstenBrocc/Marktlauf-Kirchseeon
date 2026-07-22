-- 025_schichten_anmeldung.sql
-- Kopplung Einsatzplan <-> Helferanmeldung:
--   * in_anmeldung: steuert, ob eine Schicht im oeffentlichen Anmeldeformular
--     angeboten wird (Default 1 = sichtbar; interne Aufgaben ggf. ausblenden).
--   * zeitfenster: Freitext-Label fuer das Zeitfenster (z. B. "freie Verfuegbarkeit",
--     "nach Absprache"), wenn keine feste Uhrzeit (von/bis) passt. Wird in der
--     Anmeldung angezeigt und im Einsatzplan als Fallback genutzt.

SET NAMES utf8mb4;

ALTER TABLE `schichten`
    ADD COLUMN `in_anmeldung` TINYINT(1) NOT NULL DEFAULT 1 AFTER `bedarf`,
    ADD COLUMN `zeitfenster`  VARCHAR(80) NULL DEFAULT NULL AFTER `bis`;
