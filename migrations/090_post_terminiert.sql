-- 090: Terminierter Versand (beste Sendezeit) — Status 'terminiert' + Slot-Zeitpunkt.
-- Spec intern/social-auto-versand-beste-zeit-spec.md §4b/S3/S4 (Bau-Entscheid TT 2026-08-27).
-- Ein terminierter FB-Post ist NOCH NICHT live: er wird an Meta uebergeben (scheduled_publish_time)
-- und erst zum Slot vom Timer finalisiert (-> 'gesendet' + "erste-Stunde"-Mail), weil FB zur echten
-- Veroeffentlichung nicht zurueckruft. terminiert_fuer haelt den echten Slot-Zeitpunkt, da der
-- Fahrplan-zieldatum beim Terminieren bereits vorrueckt.
-- Additiv: ENUM-Erweiterung laesst bestehende Werte unberuehrt; terminiert_fuer NULL = kein Termin.
ALTER TABLE post_race_contents
    MODIFY COLUMN status ENUM('draft','approved','gesendet','terminiert') NOT NULL DEFAULT 'draft',
    ADD COLUMN terminiert_fuer DATETIME NULL AFTER geplante_uhrzeit;
