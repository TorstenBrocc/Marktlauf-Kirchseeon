-- 037_sponsor_rechnung_betrag.sql
-- Abweichender Rechnungsbetrag je Sponsor (Ausnahme vom Paket-Listenpreis) + Brutto-Kennzeichen.
-- Normalfall: Betrag kommt aus dem gebuchten Paket (netto). Ausnahme (z. B. Sparkasse gibt
-- einen fixen Brutto-Betrag): abweichender Betrag setzen und ggf. als brutto markieren.

ALTER TABLE sponsors
  ADD COLUMN rechnung_betrag        DECIMAL(10,2) NULL          AFTER leistung_zeitraum,
  ADD COLUMN rechnung_betrag_brutto TINYINT(1)    NOT NULL DEFAULT 0 AFTER rechnung_betrag;
