-- 053_sponsor_status_bestaetigt.sql
-- Neuer Sponsor-Status 'bestaetigt' (zwischen zugesagt und abgerechnet): wird gesetzt,
-- sobald dem Sponsor die Bestaetigung zugestellt wurde (orga/bestaetigungen.php, G3).
-- Additiv: bestehende Werte bleiben unveraendert.

ALTER TABLE sponsors
  MODIFY COLUMN status ENUM('neu','angefragt','in_klaerung','zugesagt','bestaetigt','abgerechnet','bezahlt','abgelehnt')
  NOT NULL DEFAULT 'neu';
