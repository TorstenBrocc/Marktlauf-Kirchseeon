-- Migration: Add notiz column and extend status ENUM
-- Run manually on server after deploy

-- Add 'abgelehnt' to status ENUM
ALTER TABLE `helfer` MODIFY COLUMN `status` ENUM('neu', 'bestaetigt', 'abgelehnt') NOT NULL DEFAULT 'neu';

-- Add notiz column
ALTER TABLE `helfer` ADD COLUMN `notiz` TEXT NULL AFTER `status`;
