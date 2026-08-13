-- 027_helfer_slots_schicht_id.sql
-- helfer_slots referenziert kuenftig eine konkrete Schicht (schicht_id).
-- Damit ist eine Anmelde-Auswahl = Selbstmeldung fuer genau diese Schicht.
-- Die denormalisierten Spalten tag/zeitfenster/aufgabe bleiben als Snapshot
-- erhalten (robust gegen spaetere Schicht-Aenderungen; genutzt von zugang.php,
-- helfer_form.php). Bestehende Anmeldungen werden ueber (tag, aufgabe=titel,
-- zeitfenster) den in 026 angelegten Schichten zugeordnet.

SET NAMES utf8mb4;

ALTER TABLE `helfer_slots`
    ADD COLUMN `schicht_id` INT UNSIGNED NULL DEFAULT NULL AFTER `helfer_id`,
    ADD KEY `idx_slots_schicht` (`schicht_id`),
    ADD CONSTRAINT `fk_slots_schicht` FOREIGN KEY (`schicht_id`)
        REFERENCES `schichten` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- Backfill: vorhandene Selbstmeldungen den passenden Katalog-Schichten zuordnen.
UPDATE `helfer_slots` hs
JOIN `schichten` sc
    ON  sc.`tag`         = hs.`tag`
    AND sc.`titel`       = hs.`aufgabe`
    AND sc.`zeitfenster` = hs.`zeitfenster`
SET hs.`schicht_id` = sc.`id`
WHERE hs.`schicht_id` IS NULL;
