-- Migration: Split name column into vorname + nachname
-- ATSV Kirchseeon Marktlauf Dashboard

SET NAMES utf8mb4;

-- Add new columns
ALTER TABLE `helfer`
    ADD COLUMN `vorname` VARCHAR(100) NOT NULL DEFAULT '' AFTER `uuid`,
    ADD COLUMN `nachname` VARCHAR(100) NOT NULL DEFAULT '' AFTER `vorname`;

-- Migrate existing data: split "name" at first space
UPDATE `helfer`
SET
    `vorname` = TRIM(SUBSTRING_INDEX(`name`, ' ', 1)),
    `nachname` = TRIM(SUBSTRING(`name` FROM LOCATE(' ', `name`) + 1))
WHERE `name` != '';

-- Handle single-word names (no space found)
UPDATE `helfer`
SET `nachname` = ''
WHERE `nachname` = `vorname` AND LOCATE(' ', `name`) = 0;

-- Drop old column
ALTER TABLE `helfer` DROP COLUMN `name`;
