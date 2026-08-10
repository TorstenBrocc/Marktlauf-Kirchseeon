-- 055_anschreiben_typ_vollstaendig.sql
-- Das Anschreiben-Tracking kannte 'bestaetigung' und 'bedingungen' nicht: beide Typen
-- werden seit Einführung versendet (orga/api/sponsor_versand.php) und via
-- sponsorMarkGesendet() in sponsors.anschreiben_typ geschrieben — die Spalte war aber
-- noch ENUM('erstanschreiben','folgejahr','frei') aus Migration 015. Das Tracking lief
-- für diese beiden Typen ins Leere.
-- Additiv: bestehende Werte bleiben unveraendert.

ALTER TABLE sponsors
  MODIFY COLUMN anschreiben_typ
    ENUM('erstanschreiben','folgejahr','frei','bestaetigung','bedingungen') NULL;

ALTER TABLE sponsor_versand_queue
  MODIFY COLUMN anschreiben_typ
    ENUM('erstanschreiben','folgejahr','frei','bestaetigung','bedingungen')
    NOT NULL DEFAULT 'erstanschreiben';
