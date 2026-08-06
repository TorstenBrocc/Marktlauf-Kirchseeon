-- 040_dateien_jahr.sql
-- Jahres-Dimension (intern/gdrive-storage-spec.md §2.4, Paket 6):
--   1) dateien.jahr — Saison-Zuordnung je Datei. Bestehende Zeilen bekommen das
--      laufende Jahr als Default (per UPDATE unten), neue Uploads setzen es explizit.
--   2) drive_kategorie_ordner: Ordner-Cache bekommt eine Jahr-Ebene. Struktur im
--      Laufwerk = Orga/<Jahr>/<Kategorie>. PK jetzt (bereich, jahr, kategorie).
--      jahr=0 & kategorie='' = Bereichs-Wurzel (Orga/Helfer); kategorie='' = Jahres-Ordner.
--      Der bisherige (flache) Cache-Inhalt ist obsolet -> geleert; die App legt die
--      jahr-basierten Ordner neu an. (Die leeren flachen Drive-Ordner separat aufräumen.)
-- Nicht-destruktiv fürs Schema (ADD COLUMN / TRUNCATE des reinen Caches).

ALTER TABLE dateien
  ADD COLUMN jahr SMALLINT UNSIGNED NULL AFTER kategorie;

UPDATE dateien SET jahr = YEAR(CURDATE()) WHERE jahr IS NULL;

TRUNCATE TABLE drive_kategorie_ordner;

ALTER TABLE drive_kategorie_ordner
  DROP PRIMARY KEY,
  ADD COLUMN jahr SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER bereich,
  ADD PRIMARY KEY (bereich, jahr, kategorie);
