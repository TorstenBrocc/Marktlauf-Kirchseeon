-- 057_sponsors_ansprache.sql
-- Du/Sie je Sponsor (TT, 2026-08-10). Bisher war die Höflichkeitsform in der Anrede-Funktion
-- fest verdrahtet; bei örtlichen Partnern, die man persönlich kennt, klingt das falsch.
--
-- Bewusst am Sponsor, nicht am Ansprechpartner: die Ansprache ist eine Eigenschaft der
-- Geschäftsbeziehung, nicht der einzelnen Person — und ein Sponsor mit zwei Kontakten soll nicht
-- halb geduzt werden. Default 'sie', damit sich für den Bestand nichts ändert.

ALTER TABLE sponsors
  ADD COLUMN ansprache ENUM('sie','du') NOT NULL DEFAULT 'sie' AFTER kein_kontakt;
