-- 023_sponsors_zustaendig.sql
-- Zuständigkeit: Orga-/Admin-Mitglied einem Sponsoreneintrag zuordnen.

ALTER TABLE sponsors
    ADD COLUMN zustaendig_user_id INT UNSIGNED NULL DEFAULT NULL AFTER wiedervorlage;

ALTER TABLE sponsors
    ADD CONSTRAINT fk_sponsor_zustaendig
        FOREIGN KEY (zustaendig_user_id) REFERENCES users(id) ON DELETE SET NULL;
