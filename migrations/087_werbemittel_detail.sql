-- 087_werbemittel_detail.sql
-- Option B (TT, 2026-08-25): physische Werbemittel-Logistik strukturiert an der Leistungs-Matrix.
-- Bisher hielt die haken_text-Position "banner" nur ein loses Freitextfeld. Jetzt bekommt die
-- Zelle strukturierte Felder (Art/Anzahl/Deadline/Status), damit der Status je Banner in der
-- Spalte auf einen Blick sichtbar ist (Chip-Farbe). freitext bleibt als optionale Notiz erhalten.
-- Status-Kette bewusst OHNE "platziert" (TT 2026-08-25): offen -> erhalten -> zurueck.
-- Additiv, NULL-defaults: bestehende Zeilen bleiben unveraendert (kein Werbemittel-Detail gesetzt).

ALTER TABLE sponsor_leistungen
  ADD COLUMN wm_art      ENUM('banner','hussen')            NULL AFTER freitext,
  ADD COLUMN wm_anzahl   SMALLINT UNSIGNED                  NULL AFTER wm_art,
  ADD COLUMN wm_deadline DATE                               NULL AFTER wm_anzahl,
  ADD COLUMN wm_status   ENUM('offen','erhalten','zurueck') NULL AFTER wm_deadline;
