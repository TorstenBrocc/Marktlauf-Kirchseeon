-- 045_sponsors_sachsponsor.sql
-- Sachsponsoring: Kennzeichen für Sponsoren, die statt Geld eine Sachspende
-- beisteuern (z. B. Getränke, Verpflegung am Renntag). Orthogonal zum Geldpaket
-- (paket bleibt NULL), damit Rechnungs-/Preislogik unberührt bleibt. Was konkret
-- mitgebracht wurde, wird im bestehenden Freitextfeld notizen hinterlegt.

ALTER TABLE sponsors
    ADD COLUMN sachsponsor TINYINT(1) NOT NULL DEFAULT 0 AFTER paket;
