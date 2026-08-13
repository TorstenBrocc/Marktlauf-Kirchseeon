-- Branchen-Liste: "Finanzdienstleistungen & Versicherungen" und
-- "Gesundheit & Krankenkassen" werden jeweils in zwei eigene Einträge aufgeteilt.

UPDATE einstellungen
SET `value` = JSON_ARRAY(
    'Erneuerbare Energie & Photovoltaik',
    'Energieberatung',
    'Nachhaltigkeit & regionale Produkte',
    'Sportartikel & Fitness',
    'Gesundheit',
    'Krankenkassen',
    'Finanzdienstleistungen',
    'Versicherungen',
    'Vermögensverwaltung',
    'Handwerk & Bau',
    'Handel & Gastronomie',
    'Medizin & Pharma',
    'Fahrzeuge & Mobilität',
    'IT & Digitales',
    'Medien & Kommunikation',
    'Sonstige'
)
WHERE `key` = 'sponsor_branchen';

-- Vorhandene Sponsoren mit alten kombinierten Branchenwerten migrieren
-- (greift, falls der CSV-Import bereits gelaufen ist).

UPDATE sponsors SET branche = '["Krankenkassen"]'
WHERE JSON_CONTAINS(branche, '"Gesundheit & Krankenkassen"');

UPDATE sponsors SET branche = '["Finanzdienstleistungen"]'
WHERE JSON_CONTAINS(branche, '"Finanzdienstleistungen & Versicherungen"')
  AND firma IN (
    'Raiffeisen-Volksbank Ebersberg eG',
    'VR-Bank München Land eG',
    'VR Gewinnsparverein Bayern e.V.',
    'VR-Förderpreis der bayer. Volks- und Raiffeisenbanken',
    'Sparkassen-Finanzgruppe / DSGV – Sportförderung',
    'Bausparkasse Schwäbisch Hall',
    'LBS Bayern'
  );

UPDATE sponsors SET branche = '["Versicherungen"]'
WHERE JSON_CONTAINS(branche, '"Finanzdienstleistungen & Versicherungen"')
  AND firma IN (
    'Versicherungskammer Bayern',
    'Versicherungskammer Stiftung (Ehrenamtstiftung)',
    'die Bayerische (Versicherungsgruppe)',
    'Allianz',
    'ARAG (Sportversicherungsbüro BLSV)',
    'Barmenia (Barmenia·Gothaer)'
  );
