-- 064_drop_sponsor_aufgaben.sql
--
-- DESTRUKTIV, mit ausdrücklicher Freigabe des Inhabers (2026-08-13: „ja,
-- sponsor_aufgaben-Tabelle löschen").
--
-- Die Tabelle ist seit Migration 063 leergelaufen: ihr Bestand liegt in `aufgaben`
-- (kontext_typ='sponsor'), und seit PR #27 liest und schreibt kein Code sie mehr —
-- der letzte Zugriffspunkt `orga/api/aufgabe_crud.php` ist entfallen.
--
-- Vor dem Drop auf Prod verifiziert: 2 Zeilen in sponsor_aufgaben, 2 Zeilen in
-- aufgaben mit kontext_typ='sponsor', 0 Zeilen ohne Entsprechung. Es geht also
-- nichts verloren, was nicht bereits übernommen wäre.
--
-- Sicherheitsnetz beim Zurückrollen: Ein Rückweg über `git revert` allein genügt
-- nicht, weil die Daten dann fehlen. Wer zurück muss, spielt 063 aus dem Backup
-- zurück — die übernommenen Zeilen in `aufgaben` bleiben davon unberührt.

DROP TABLE sponsor_aufgaben;
