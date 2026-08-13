-- Migration: Remove UNIQUE constraint on helfer.email
-- Reason: Multiple helpers can share same email (e.g. families)
-- Run manually on server before deploying

ALTER TABLE `helfer` DROP INDEX `uk_helfer_email`;
