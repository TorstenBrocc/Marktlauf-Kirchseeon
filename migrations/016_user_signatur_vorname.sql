-- 016_user_signatur_vorname.sql
-- Benutzer-Profil: Telefon + Aufgabe für Sponsorenbrief-Signatur
-- Versand-Queue: Vorname für {{vorname}}-Platzhalter

ALTER TABLE users
  ADD COLUMN telefon VARCHAR(50)  NULL AFTER email,
  ADD COLUMN aufgabe VARCHAR(150) NULL AFTER telefon;

ALTER TABLE sponsor_versand_queue
  ADD COLUMN vorname VARCHAR(128) NOT NULL DEFAULT '' AFTER nachname;
