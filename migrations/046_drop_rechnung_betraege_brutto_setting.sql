-- 046_drop_rechnung_betraege_brutto_setting.sql
-- Aufräumen: der globale netto/brutto-Schalter wurde entfernt (Pakete sind immer netto,
-- Ausnahme ist der Pro-Sponsor-Brutto-Haken). Die zugehörige Einstellungs-Zeile wird von
-- keinem Code mehr gelesen und wird hier entfernt, damit nichts verwaist zurückbleibt.

DELETE FROM einstellungen WHERE `key` = 'rechnung_betraege_brutto';
