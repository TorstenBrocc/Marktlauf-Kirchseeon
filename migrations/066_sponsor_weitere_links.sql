-- Weitere frei hinterlegbare Links je Sponsor (zusätzlich zur Fördermaske/quellenurl).
-- Additiv. Freitext, eine Zeile pro Link, optional "Beschriftung | https://…".
ALTER TABLE sponsors ADD COLUMN weitere_links TEXT NULL AFTER quellenurl;
