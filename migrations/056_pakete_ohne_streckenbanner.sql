-- 056_pakete_ohne_streckenbanner.sql
-- „Logo auf Streckenbanner" ist als Leistung gestrichen (TT, 2026-08-10) — die Position war
-- zudem kein Logo-, sondern ein Banner-Thema und wurde deshalb auf der Rechnung falsch mit den
-- Logo-Platzierungen zusammengefasst. Der Katalog kennt sie nicht mehr (Code); hier verschwindet
-- sie zusätzlich aus den Paket-Highlights, damit der Sponsorenbrief keine Leistung mehr anbietet,
-- die es nicht gibt.
--
-- Wie bei Migration 054 wird nur der betroffene Textteil herausgeschnitten, nicht der ganze
-- JSON-Wert überschrieben — der Pflegestand aller anderen Paket-Felder bleibt unangetastet.
-- Im Bestand steht „Logo auf Startnummer & Streckenbanner"; abgedeckt sind auch die Varianten
-- mit Komma, falls der Text zwischenzeitlich anders gepflegt wurde.

UPDATE einstellungen
SET `value` = REPLACE(REPLACE(REPLACE(REPLACE(`value`,
                ' & Streckenbanner', ''),
                ', Logo auf Streckenbanner', ''),
                'Logo auf Streckenbanner, ', ''),
                'Logo auf Streckenbanner', '')
WHERE `key` = 'sponsoring_pakete';

-- Matrix-Zeilen zu der gestrichenen Position aufräumen (beim Anwenden: 0 Zeilen vorhanden).
DELETE FROM sponsor_leistungen WHERE position = 'logo_streckenbanner';

-- „Logo auf Lauf-Shirt" ist 2026 nicht im Angebot (kein Lauf-Shirt), bleibt aber als Position
-- im Katalog stehen (`aktiv => false`) und ist mit einem Handgriff wieder aktivierbar.
-- Die drei vorhandenen Zeilen sagen „nicht vereinbart" und tragen keinen Freitext (geprüft) —
-- sie sind gegenstandslos und würden bei einer Reaktivierung als altes „abgewählt" wirken.
DELETE FROM sponsor_leistungen WHERE position = 'logo_shirt' AND vereinbart = 0 AND (freitext IS NULL OR freitext = '');
