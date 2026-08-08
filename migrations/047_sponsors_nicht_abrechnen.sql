-- 047_sponsors_nicht_abrechnen.sql
-- Kennzeichen, um einen zugesagten Sponsor MIT Paket bewusst aus der Abrechnung zu nehmen,
-- ohne den Datensatz zu löschen (reversibel). Steuert nur die "Abzurechnen"-Liste auf
-- orga/rechnungen.php; alle anderen Sponsor-Ansichten bleiben unberührt.

ALTER TABLE sponsors
  ADD COLUMN nicht_abrechnen TINYINT(1) NOT NULL DEFAULT 0 AFTER rechnung_betrag_brutto;
