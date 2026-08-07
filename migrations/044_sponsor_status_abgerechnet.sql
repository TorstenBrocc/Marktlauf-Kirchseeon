-- 044_sponsor_status_abgerechnet.sql
-- Neuer Sponsor-Status 'abgerechnet' (zwischen zugesagt und bezahlt): wird gesetzt,
-- sobald für den Sponsor eine Rechnung erzeugt wurde.

ALTER TABLE sponsors
  MODIFY COLUMN status ENUM('neu','angefragt','in_klaerung','zugesagt','abgerechnet','bezahlt','abgelehnt')
  NOT NULL DEFAULT 'neu';
