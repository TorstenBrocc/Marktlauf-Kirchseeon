-- Pro-Sponsor-Entwürfe: sponsor_id-Dimension am persönlichen Brief-Entwurf.
-- Motiv: Die Sponsoren-Bestätigung soll je Sponsor ihren eigenen zuletzt bearbeiteten
-- Stand behalten (Autosave), statt eine einzige Vorlage für alle zu teilen.
--   sponsor_id = 0  → allgemeine Vorlage (unverändertes Verhalten aller übrigen Anschreiben).
--   sponsor_id > 0  → individuell gespeicherter Stand genau dieses Sponsors (nur Bestätigung).
-- Additiv: bestehende Zeilen erhalten sponsor_id = 0, der Unique-Key bleibt damit gültig.
ALTER TABLE briefvorlagen_entwurf
    ADD COLUMN sponsor_id INT UNSIGNED NOT NULL DEFAULT 0 AFTER slug;

ALTER TABLE briefvorlagen_entwurf
    DROP INDEX uq_user_vorlage,
    ADD UNIQUE KEY uq_user_vorlage (user_id, vorlage_art, slug, sponsor_id);
