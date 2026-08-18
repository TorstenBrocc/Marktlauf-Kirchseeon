-- 078: Reminder-Zeitplan von Preset auf Wochentagsliste umgestellt (TT, 2026-08-18).
-- Der Key `reminder_frequenz` (taeglich/werktags/di_fr/freitags) ist durch
-- `reminder_versandtage` (ISO-Wochentagsliste, z. B. "1,2,3,4,5") + `reminder_pause_bis`
-- ersetzt; Code liest den alten Key nicht mehr. Bestehender Wert wird in die
-- äquivalente Tagesliste überführt, dann der alte Key entfernt.

INSERT INTO einstellungen (`key`, `value`)
SELECT 'reminder_versandtage',
       CASE e.`value`
           WHEN 'werktags' THEN '1,2,3,4,5'
           WHEN 'di_fr'    THEN '2,5'
           WHEN 'freitags' THEN '5'
           ELSE '1,2,3,4,5,6,7'
       END
FROM einstellungen e
WHERE e.`key` = 'reminder_frequenz'
  AND e.`value` IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM (SELECT `key` FROM einstellungen WHERE `key` = 'reminder_versandtage') x
  );

DELETE FROM einstellungen WHERE `key` = 'reminder_frequenz';
