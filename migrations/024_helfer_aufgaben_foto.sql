-- 024_helfer_aufgaben_foto.sql
-- 1) helfer_slots: granulare Aufgaben statt nur vormittag/nachmittag
--    zeitfenster wird Freitext (z. B. "07:00-10:00", "nach Absprache"),
--    neue Spalte aufgabe für die Aufgabenbeschreibung.
-- 2) helfer: Fotoeinwilligung (DSGVO Art. 6 Abs. 1 lit. a).

ALTER TABLE `helfer_slots`
    MODIFY COLUMN `zeitfenster` VARCHAR(80) NOT NULL;

ALTER TABLE `helfer_slots`
    ADD COLUMN `aufgabe` VARCHAR(255) NULL DEFAULT NULL AFTER `zeitfenster`;

ALTER TABLE `helfer`
    ADD COLUMN `is_minor` TINYINT(1) NOT NULL DEFAULT 0 AFTER `notiz`,
    ADD COLUMN `consent_photo` ENUM('yes','no') NOT NULL DEFAULT 'no' AFTER `is_minor`,
    ADD COLUMN `guardian_name` VARCHAR(255) NULL DEFAULT NULL AFTER `consent_photo`,
    ADD COLUMN `consent_ts` DATETIME NULL DEFAULT NULL AFTER `guardian_name`;
