-- Post-Objekt fuer das Social-Redesign (Schnitt 2, Spec: intern/social-fahrplan-redesign-spec.md):
-- post_race_contents traegt Anlass und das Ergebnis der KI-Gegenpruefung.
ALTER TABLE post_race_contents
  ADD COLUMN anlass_key        VARCHAR(64) NULL AFTER race_id,
  ADD COLUMN geprueft_am       DATETIME    NULL AFTER status,
  ADD COLUMN geprueft_provider VARCHAR(16) NULL AFTER geprueft_am,
  ADD COLUMN geprueft_ergebnis MEDIUMTEXT  NULL AFTER geprueft_provider;
