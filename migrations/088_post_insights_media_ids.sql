-- make.com-Optimierung Stage C (Insights-Sammler, Spec §7): speichert die von make gemeldeten
-- IG/FB-Media-IDs, damit ein getrenntes geplantes Szenario spaeter je Post die Insights
-- (Reichweite/Likes) nachladen und via post_status_callback.php zurueckmelden kann.
-- Quelle: der Erfolgs-Callback (Stage 1) sendet zusaetzlich `media_id`. Additiv + NULL.
ALTER TABLE post_race_contents
    ADD COLUMN ig_media_id VARCHAR(64) NULL,
    ADD COLUMN fb_post_id  VARCHAR(64) NULL;
