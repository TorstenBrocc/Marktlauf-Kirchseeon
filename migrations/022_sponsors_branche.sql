ALTER TABLE sponsors ADD COLUMN branche VARCHAR(100) NULL DEFAULT NULL AFTER ort;

INSERT INTO einstellungen (`key`, `value`)
VALUES ('sponsor_branchen', JSON_ARRAY(
    'Erneuerbare Energie & Photovoltaik',
    'Energieberatung',
    'Nachhaltigkeit & regionale Produkte',
    'Sportartikel & Fitness',
    'Gesundheit & Krankenkassen',
    'Finanzdienstleistungen & Versicherungen',
    'Vermögensverwaltung',
    'Handwerk & Bau',
    'Handel & Gastronomie',
    'Medizin & Pharma',
    'Fahrzeuge & Mobilität',
    'IT & Digitales',
    'Medien & Kommunikation',
    'Sonstige'
))
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);
