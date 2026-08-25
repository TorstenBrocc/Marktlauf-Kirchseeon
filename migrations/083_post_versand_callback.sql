-- make.com-Optimierung #1 (Erfolgs-Rueckkanal) + #2 (Permalink) — Post-Wirkung-Spec.
-- Bisher wusste das Dashboard nur "an Make.com uebergeben" (HTTP 2xx), nicht ob/wo IG/FB
-- wirklich gepostet hat. Make.com meldet nach dem echten Post per Callback
-- (orga/api/post_status_callback.php) den Live-Permalink je Kanal zurueck.
-- Alle Spalten additiv + NULL: nicht-destruktiv, brechen bestehende Zeilen nicht.
ALTER TABLE post_race_contents
    ADD COLUMN ig_permalink          VARCHAR(255) NULL,
    ADD COLUMN fb_permalink          VARCHAR(255) NULL,
    ADD COLUMN versand_bestaetigt_am DATETIME     NULL,
    ADD COLUMN versand_callback_info VARCHAR(400) NULL;
