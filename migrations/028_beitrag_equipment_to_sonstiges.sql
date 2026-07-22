-- 028_beitrag_equipment_to_sonstiges.sql
-- "Equipment" wird mit "Sonstiges" zu EINEM Punkt "Sonstige Unterstützung"
-- zusammengefasst. Bestehende equipment-Eintraege werden in sonstiges gefaltet
-- (Freitext-Marker, damit die Herkunft nachvollziehbar bleibt), danach faellt
-- 'equipment' aus dem ENUM.

SET NAMES utf8mb4;

UPDATE `helfer_beitrag`
SET `typ` = 'sonstiges',
    `freitext` = CONCAT(IFNULL(`freitext`, ''), IF(`freitext` IS NOT NULL AND `freitext` != '', ' (Equipment)', 'Equipment'))
WHERE `typ` = 'equipment';

ALTER TABLE `helfer_beitrag`
    MODIFY COLUMN `typ` ENUM('kuchen', 'sonstiges') NOT NULL;
