-- Grafik haengt am Post (Schnitt 3, Spec: intern/social-fahrplan-redesign-spec.md):
-- Pfad relativ zur Website-Wurzel, Datei liegt unter assets/social/ (Deploy-EXCLUDE).
ALTER TABLE post_race_contents
  ADD COLUMN bild_pfad VARCHAR(255) NULL AFTER geprueft_ergebnis;
