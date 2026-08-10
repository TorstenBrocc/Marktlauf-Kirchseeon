-- 054_pakete_ohne_startertueten.sql
-- „Startertüten-Branding" ist als Sponsoring-Leistung entfallen (TT, 2026-08-10): raus aus der
-- Katalog-Position (Code, Migration nicht nötig — die Leistungs-Matrix enthielt dazu keine Zeile)
-- und raus aus den Paket-Highlights, die im Sponsorenbrief die Pakettabelle füllen.
--
-- Die Highlights liegen als JSON in der Einstellung `sponsoring_pakete`. Statt den ganzen Wert zu
-- überschreiben (und damit alle anderen Pflegestände von TT zu plattzumachen) wird nur der eine
-- Posten herausgeschnitten. Beide im Bestand vorkommenden Schreibweisen sind abgedeckt:
-- „Startertüten-Branding" (Datenbank) und „Startetüten-Branding" (frühere Code-Defaults).
-- Reihenfolge: erst die Variante mit nachfolgendem Komma, dann die mit führendem, dann der Rest.

UPDATE einstellungen
SET `value` = REPLACE(REPLACE(REPLACE(
                REPLACE(REPLACE(REPLACE(`value`,
                  'Startertüten-Branding, ', ''),
                  ', Startertüten-Branding', ''),
                  'Startertüten-Branding', ''),
                  'Startetüten-Branding, ', ''),
                  ', Startetüten-Branding', ''),
                  'Startetüten-Branding', '')
WHERE `key` = 'sponsoring_pakete';

-- Aufräumen: Matrix-Zeilen zu der entfallenen Position, falls doch welche entstanden sind.
-- Beim Anwenden waren es 0 Zeilen (geprüft) — die Anweisung hält den Stand trotzdem konsistent.
DELETE FROM sponsor_leistungen WHERE position = 'startertueten';
