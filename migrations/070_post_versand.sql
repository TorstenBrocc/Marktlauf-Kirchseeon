-- Versand am Post (Schnitt 4, Spec: intern/social-fahrplan-redesign-spec.md):
-- Status 'gesendet' + Versand-Log. ENUM-Erweiterung ist additiv (bestehende Werte bleiben).
ALTER TABLE post_race_contents
  MODIFY COLUMN status ENUM('draft','approved','gesendet') NOT NULL DEFAULT 'draft',
  ADD COLUMN gesendet_am       DATETIME     NULL AFTER bild_pfad,
  ADD COLUMN gesendet_kanaele  VARCHAR(64)  NULL AFTER gesendet_am,
  ADD COLUMN gesendet_ergebnis VARCHAR(255) NULL AFTER gesendet_kanaele;
