-- 060_versand_queue_anhang_abwahl.sql
--
-- UMNUMMERIERT 2026-08-12 (vorher 056): parallel war 056_pakete_ohne_streckenbanner.sql
-- entstanden, die Nummer 056 also doppelt vergeben. Der Migrator führt Buch über den
-- DATEINAMEN, nicht über die Nummer — eine Umbenennung gilt deshalb als neue, offene
-- Migration und hätte das ALTER TABLE unten erneut gefahren (Fehler: Spalte existiert).
-- Deshalb wurde nach dem Umbenennen auf Prod EINMALIG `php bin/migrate.php baseline`
-- gefahren, das den neuen Dateinamen als angewendet einträgt, ohne ihn auszuführen.
-- Die Zeile mit dem alten Dateinamen bleibt in `schema_migrations` stehen: sie ist die
-- wahrheitsgemäße Historie, dass diese Migration am 2026-08-10 unter 056 gelaufen ist,
-- und wird von `status`/`migrate` nie wieder angefasst (beide gehen von den Dateien aus).
--
-- Für frische Installationen ändert die neue Nummer nichts: die Migration hängt nur an
-- `sponsor_versand_queue` (Migration 012) und ist von 057–059 unabhängig.
-- Anhang-Abwahl auch im Mehrfachversand: Beim Einzelversand kamen die abgewählten
-- Drive-Datei-IDs direkt im POST an; ging der Versand über die Sende-Queue (ab 2 Empfängern),
-- fiel die Abwahl still unter den Tisch — bin/sponsor_versand.php übergab leere Listen.
-- Die Auswahl wird jetzt je Queue-Eintrag als JSON-Array mitgeschrieben.

ALTER TABLE sponsor_versand_queue
  ADD COLUMN exclude_plakat_fids TEXT NULL AFTER anschreiben_typ,
  ADD COLUMN exclude_asset_fids  TEXT NULL AFTER exclude_plakat_fids;
