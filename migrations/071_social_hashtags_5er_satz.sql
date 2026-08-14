-- Gegrillter Hashtag-Standardsatz (Inhaber-Go 2026-08-14, Spec social-fahrplan-redesign-spec.md 7.1):
-- ersetzt den alten 13er-Satz. 1 Marken-Tag + 2 lokale + 2 thematische (IG-5er-Limit).
INSERT INTO einstellungen (`key`, `value`)
VALUES ('social_hashtags', '#marktlaufkirchseeon #kirchseeon #ebersberg #volkslauf #laufenverbindet')
ON DUPLICATE KEY UPDATE `value` = '#marktlaufkirchseeon #kirchseeon #ebersberg #volkslauf #laufenverbindet';
