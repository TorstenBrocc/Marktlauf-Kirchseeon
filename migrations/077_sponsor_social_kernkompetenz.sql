-- Post-Wirkung-Spec S5: SSOT-Felder am Sponsor fuer die Social-Kopplung.
--   social_handle: IG/FB-Handle. Schaltet die Tag-/Collab-Erinnerung frei (Verstaerker-Liste
--     + Post-live-Mail). Der Meta-Handgriff (markieren/als Collab einladen) bleibt manuell.
--   kernkompetenz: knappe Kernkompetenz des Sponsors (SSOT). Die KI stellt daraus den
--     Marktlauf-Bezug selbst her ("Baeckerei X bringt die Brezn ins Ziel"). Leer erlaubt —
--     die Daten werden nachgezogen, der Bau ist davon nicht blockiert (Spec §8).
-- Beide Spalten additiv + NULL: nicht-destruktiv, brechen bestehende Zeilen nicht.
ALTER TABLE sponsors
    ADD COLUMN social_handle VARCHAR(80) NULL,
    ADD COLUMN kernkompetenz VARCHAR(200) NULL;
