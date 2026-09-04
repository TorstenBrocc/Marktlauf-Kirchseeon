-- TikTok-Integration WP-T1 (Spec intern/social-tiktok-integration-spec.md §5, Inhaber-Entscheid
-- 2026-09-04, Weg B): TikTok als Kanal ins Post-Datenmodell. Analog zu den IG/FB-Spalten
-- (Media-ID 088, Permalink 083, Insights 085), aber mit vier Kennzahlen, die die TikTok
-- Display-API (v2 /video/query) liefert: view_count/like_count/comment_count/share_count.
-- tt_reichweite haelt view_count (bei TikTok faktisch die View-Zahl, im UI als "Views" gelabelt).
-- tt_video_id = die nach dem Publish gesetzte Video-ID (Anker fuer den Insights-Poll, WP-T3).
-- tt_video_pfad = die fertige, in CapCut gerenderte MP4 (Quelle fuer PULL_URL beim Posten, WP-T2).
-- Alle Spalten additiv + NULL => Bestandsposts unveraendert.
ALTER TABLE post_race_contents
    ADD COLUMN tt_video_id   VARCHAR(64)  NULL,
    ADD COLUMN tt_permalink  VARCHAR(255) NULL,
    ADD COLUMN tt_reichweite INT          NULL,
    ADD COLUMN tt_likes      INT          NULL,
    ADD COLUMN tt_kommentare INT          NULL,
    ADD COLUMN tt_shares     INT          NULL,
    ADD COLUMN tt_video_pfad VARCHAR(255) NULL;
