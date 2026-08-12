-- 056_versand_queue_anhang_abwahl.sql
--
-- HINWEIS ZUR NUMMER: Es gibt zwei 056er — parallel entstand
-- 056_pakete_ohne_streckenbanner.sql in einer anderen Session. Beide sind additiv,
-- voneinander unabhängig und auf Prod angewendet; der Migrator führt Buch über den
-- Dateinamen, nicht über die Nummer. Nicht nachträglich umbenennen: eine Umbenennung
-- gälte als neue, offene Migration und würde das ALTER TABLE erneut fahren.
-- Anhang-Abwahl auch im Mehrfachversand: Beim Einzelversand kamen die abgewählten
-- Drive-Datei-IDs direkt im POST an; ging der Versand über die Sende-Queue (ab 2 Empfängern),
-- fiel die Abwahl still unter den Tisch — bin/sponsor_versand.php übergab leere Listen.
-- Die Auswahl wird jetzt je Queue-Eintrag als JSON-Array mitgeschrieben.

ALTER TABLE sponsor_versand_queue
  ADD COLUMN exclude_plakat_fids TEXT NULL AFTER anschreiben_typ,
  ADD COLUMN exclude_asset_fids  TEXT NULL AFTER exclude_plakat_fids;
