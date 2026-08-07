-- 042_sponsor_logo_rotation.sql
-- Öffentliche Sponsoren-Logo-Rotation, datengetrieben aus dem Dashboard.
-- Spec: intern/sponsoren-logo-rotation-spec.md
--
-- Additiv, nicht-destruktiv (nur ADD COLUMN) — kann vor dem abhängigen Code
-- gefahren werden, ohne Bestand zu berühren. Default hält Sponsoren aus der
-- Rotation, bis sie bewusst aktiviert werden (rückwärtskompatibel).
--
--   in_rotation        -- der "aktiv in Rotation"-Steuerknopf (Auswahlfeld)
--   logo_web_asset     -- Dateiname des materialisierten, web-optimierten Logos
--                         (in einem deploy-ausgeschlossenen, öffentlichen Verzeichnis)
--   logo_drive_file_id -- optionale Quelldatei im Drive-Ordner (falls Logo von dort gewählt)
--   drive_folder_id    -- Drive-Ordner des Sponsors (Ablagebecken; für Auto-Anlage/Pick)
ALTER TABLE `sponsors`
    ADD COLUMN `in_rotation`        TINYINT(1)   NOT NULL DEFAULT 0 AFTER `website`,
    ADD COLUMN `logo_web_asset`     VARCHAR(255) NULL           AFTER `in_rotation`,
    ADD COLUMN `logo_drive_file_id` VARCHAR(128) NULL           AFTER `logo_web_asset`,
    ADD COLUMN `drive_folder_id`    VARCHAR(128) NULL           AFTER `logo_drive_file_id`;
