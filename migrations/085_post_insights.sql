-- make.com-Optimierung MO1 (Insights-Rueckkanal): Reichweite/Likes je Post + Kanal, vom
-- verzoegerten make.com-Callback (orga/api/post_status_callback.php) gemeldet. Snapshot =
-- letzter Wert, kein Verlauf (Spec intern/make-com-optimierung-spec.md §1.3, Inhaber-Entscheid
-- 2026-08-25). Getrennt je Kanal wie die Permalinks (Migration 083). Additiv + NULL.
ALTER TABLE post_race_contents
    ADD COLUMN ig_reichweite       INT      NULL,
    ADD COLUMN ig_likes            INT      NULL,
    ADD COLUMN fb_reichweite       INT      NULL,
    ADD COLUMN fb_likes            INT      NULL,
    ADD COLUMN versand_insights_am DATETIME NULL;
