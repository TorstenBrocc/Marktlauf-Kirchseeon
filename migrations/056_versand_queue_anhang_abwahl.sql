-- 056_versand_queue_anhang_abwahl.sql
-- Anhang-Abwahl auch im Mehrfachversand: Beim Einzelversand kamen die abgewählten
-- Drive-Datei-IDs direkt im POST an; ging der Versand über die Sende-Queue (ab 2 Empfängern),
-- fiel die Abwahl still unter den Tisch — bin/sponsor_versand.php übergab leere Listen.
-- Die Auswahl wird jetzt je Queue-Eintrag als JSON-Array mitgeschrieben.

ALTER TABLE sponsor_versand_queue
  ADD COLUMN exclude_plakat_fids TEXT NULL AFTER anschreiben_typ,
  ADD COLUMN exclude_asset_fids  TEXT NULL AFTER exclude_plakat_fids;
