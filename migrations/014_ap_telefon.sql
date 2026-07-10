-- 014_ap_telefon.sql
-- Telefonnummer für Ansprechpartner

ALTER TABLE sponsor_ansprechpartner
  ADD COLUMN telefon VARCHAR(64) NOT NULL DEFAULT '' AFTER email;
