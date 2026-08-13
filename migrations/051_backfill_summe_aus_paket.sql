-- 051_backfill_summe_aus_paket.sql
-- Bestandsdaten: Tier-Sponsoren (gold/silber/bronze) ohne Betrag bekommen den Pakettarif ins
-- summe-Feld, damit Summenspalte + "Zusagen gesamt" stimmen. Nur leere/0-Werte werden gefüllt,
-- bewusst gesetzte abweichende Beträge bleiben unangetastet. Preise = aktuelle Einstellung.

UPDATE sponsors SET summe = 1000 WHERE paket = 'gold'   AND (summe IS NULL OR summe = 0);
UPDATE sponsors SET summe = 500  WHERE paket = 'silber' AND (summe IS NULL OR summe = 0);
UPDATE sponsors SET summe = 250  WHERE paket = 'bronze' AND (summe IS NULL OR summe = 0);
