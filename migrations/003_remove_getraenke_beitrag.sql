-- Migration: Remove 'getraenke' from helfer_beitrag enum
-- ATSV Marktlauf Kirchseeon Dashboard

SET NAMES utf8mb4;

-- Migrate existing 'getraenke' entries to 'sonstiges'
UPDATE `helfer_beitrag`
SET `typ` = 'sonstiges',
    `freitext` = CONCAT(IFNULL(`freitext`, ''), IF(`freitext` IS NOT NULL AND `freitext` != '', ' (urspr. Getränke)', '(Getränke)'))
WHERE `typ` = 'getraenke';

-- Alter ENUM to remove 'getraenke'
ALTER TABLE `helfer_beitrag`
MODIFY COLUMN `typ` ENUM('kuchen', 'equipment', 'sonstiges') NOT NULL;
