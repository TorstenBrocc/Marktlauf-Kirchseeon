-- 062_sponsor_bedingungen_bestaetigt.sql
-- Bestaetigung der Sponsoring-Bedingungen je Sponsor: ob/wann/auf welchem Weg der Sponsor
-- die Bedingungen bestaetigt hat, und ob die Rueckmeldung im Sponsor-Drive-Ordner abgelegt ist.
-- Additiv, NULL-defaults: bestehende Sponsoren bleiben unveraendert (Tri-State faellt auf 'neutral').

ALTER TABLE sponsors
  ADD COLUMN bedingungen_bestaetigt_am DATETIME NULL,
  ADD COLUMN bedingungen_weg VARCHAR(20) NULL,
  ADD COLUMN bedingungen_beleg TINYINT(1) NOT NULL DEFAULT 0;
