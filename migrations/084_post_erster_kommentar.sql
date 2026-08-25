-- make.com-Optimierung: Erster-Kommentar-Automatik (Spec intern/make-com-optimierung-spec.md §2).
-- Reichweiten-Handgriff: Link + Hashtags wandern aus der Caption in den ERSTEN Kommentar
-- (haelt die Caption sauber). Das Feld geht als `first_comment` in den make.com-Webhook; Make
-- setzt es nach dem Post als ersten Kommentar. Additiv + NULL: nicht-destruktiv.
ALTER TABLE post_race_contents
    ADD COLUMN erster_kommentar TEXT NULL;
