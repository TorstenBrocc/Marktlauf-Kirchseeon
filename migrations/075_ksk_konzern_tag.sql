-- 075_ksk_konzern_tag.sql
-- Konzern-Tag „Kreissparkasse (KSK)" (TT-Freigabe 2026-08-14): macht in der Übersicht sichtbar,
-- dass Bank (Fördergruppe Sponsoring, Gold) und Stiftung (Förderanträge, id 104) zur selben
-- Familie gehören — für die Nachvollziehbarkeit der Einordnung. Nutzt die vorhandene
-- Konzern-Tag-Mechanik (sponsor_gruppen + sponsors.gruppe_id, Migration 030).
--
-- Additiv/defensiv: Gruppe guarded anlegen; gruppe_id nur setzen, wo noch keine Gruppe hängt
-- (überschreibt keine bestehende Zuordnung). Stiftung über die bekannte id 104, die Bank über
-- eine konservative Firma-Übereinstimmung. Trifft der Firma-Filter die Bank nicht, bleibt sie
-- schlicht ungetaggt (dann im UI von Hand nachziehen) — nichts Falsches wird getaggt.

INSERT INTO sponsor_gruppen (name)
SELECT 'Kreissparkasse (KSK)'
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM (SELECT id FROM sponsor_gruppen WHERE name = 'Kreissparkasse (KSK)' LIMIT 1) AS vorhanden
);

SET @ksk_gruppe = (
  SELECT id FROM sponsor_gruppen WHERE name = 'Kreissparkasse (KSK)' ORDER BY id LIMIT 1
);

UPDATE sponsors
SET gruppe_id = @ksk_gruppe
WHERE @ksk_gruppe IS NOT NULL
  AND gruppe_id IS NULL
  AND (id = 104 OR firma LIKE 'Kreissparkasse%');
