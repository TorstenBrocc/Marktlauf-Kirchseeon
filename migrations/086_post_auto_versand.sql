-- Auto-Versand am Stichtag (Spec intern/social-auto-versand-stichtag-spec.md §2, Inhaber 2026-08-25).
-- Opt-in je Post: NUR angehakte, freigegebene (status='approved') und faellige Posts sendet der
-- Timer (bin/social_versand.php) automatisch — der Klick-Weg bleibt Default. auto_versand_channels
-- haelt die fuer den Timer gespeicherte Kanalwahl (CSV), da der manuelle Klick sie zur Laufzeit
-- waehlt. Additiv; auto_versand NOT NULL DEFAULT 0 => Bestandsposts bleiben manuell.
ALTER TABLE post_race_contents
    ADD COLUMN auto_versand          TINYINT(1)  NOT NULL DEFAULT 0,
    ADD COLUMN auto_versand_channels VARCHAR(40) NULL;
