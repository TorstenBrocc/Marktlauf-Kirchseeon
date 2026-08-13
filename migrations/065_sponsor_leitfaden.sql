-- Leitfaden-Datei je Sponsor (z. B. ausgefüllte Sponsoring-Anfrage/Ausfüllhilfe).
-- Additiv, nicht destruktiv. Die Datei selbst liegt web-gesperrt unter
-- storage/files/leitfaeden/ (steht in deploy EXCLUDE, überlebt rsync --delete);
-- Auslieferung ausschließlich über orga/api/leitfaden_download.php (Login-Guard).
-- Die Spalte hält nur den gespeicherten Dateinamen, keinen Pfad.
ALTER TABLE sponsors ADD COLUMN leitfaden_datei VARCHAR(255) NULL AFTER quellenurl;
