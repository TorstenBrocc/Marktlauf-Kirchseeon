-- 089: Geplante Uhrzeit je Post für den „beste Sendezeit"-Timer.
-- Additiv, nicht-destruktiv. Datum kommt weiter aus social_fahrplan.zieldatum;
-- der Zeitpunkt ist zieldatum + geplante_uhrzeit. NULL = keine Wunschzeit gesetzt
-- (Fallback = bisheriges Verhalten „mittags" aus dem Cron-Zeitpunkt).
-- Spec: intern/social-auto-versand-beste-zeit-spec.md (E5, Bau-Schnitt S2).
ALTER TABLE post_race_contents
    ADD COLUMN geplante_uhrzeit TIME NULL AFTER auto_versand_channels;
