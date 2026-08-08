-- 048_drop_sponsors_nicht_abrechnen.sql
-- Rückbau: das Kennzeichen nicht_abrechnen (047) war für einen verworfenen Ansatz gedacht.
-- Die Abrechnung wird jetzt allein über das Betrag-Feld (summe) gesteuert. Spalte entfernen,
-- damit nichts Verwaistes zurückbleibt.

ALTER TABLE sponsors DROP COLUMN nicht_abrechnen;
