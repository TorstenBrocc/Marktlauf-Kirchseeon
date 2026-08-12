-- 059_leistungs_katalog.sql
-- Der Leistungs-Katalog zieht aus dem PHP-Array in src/sponsor_leistungen.php in die DB, damit
-- die Pakete mit Blick auf die Folgejahre zusammenklickbar bleiben (TT 2026-08-12): Zuordnung
-- Position -> Mindest-Paketstufe, Stueckzahlen und der Saison-Schalter `aktiv` sind dann Daten,
-- keine Code-Aenderung mehr.
--
-- Additiv. Der PHP-Katalog bleibt als Fallback bestehen: fehlt die Tabelle oder ist sie leer,
-- laeuft alles unveraendert weiter. Der Seed unten spiegelt exakt den Code-Stand vom 2026-08-12.

CREATE TABLE IF NOT EXISTS leistungs_katalog (
  `key`         VARCHAR(40)  NOT NULL,
  label         VARCHAR(120) NOT NULL,
  min_stufe     ENUM('bronze','silber','gold','hauptsponsor') NOT NULL DEFAULT 'bronze',
  typ           ENUM('haken','haken_text','startplaetze') NOT NULL DEFAULT 'haken',
  -- Zusammenfassung auf der Rechnung ("Logo auf Website, Startnummer & Urkunde").
  gruppe        VARCHAR(40)  NULL,
  kurz          VARCHAR(60)  NULL,
  gruppe_rang   INT          NULL,
  -- Stueckzahl je Paketstufe; NULL = keine Zahl (Hauptsponsor: individuell).
  menge_bronze  INT          NULL,
  menge_silber  INT          NULL,
  menge_gold    INT          NULL,
  -- 0 = in dieser Saison nicht angeboten: weder Matrix noch Rechnung, bleibt aber dokumentiert.
  aktiv         TINYINT(1)   NOT NULL DEFAULT 1,
  -- Spaltenreihenfolge der Leistungs-Matrix.
  sortierung    INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO leistungs_katalog
  (`key`, label, min_stufe, typ, gruppe, kurz, gruppe_rang, menge_bronze, menge_silber, menge_gold, aktiv, sortierung)
VALUES
  ('logo_website',     'Logo auf Website',            'bronze', 'haken',        'logo', 'Website',     1,    NULL, NULL, NULL, 1, 10),
  ('urkunde',          'Logo auf Urkunde',            'bronze', 'haken',        'logo', 'Urkunde',     4,    NULL, NULL, NULL, 1, 20),
  ('dankesschreiben',  'Dankesschreiben',             'bronze', 'haken',        NULL,   NULL,          NULL, NULL, NULL, NULL, 1, 30),
  ('logo_startnummer', 'Logo auf Startnummer',        'silber', 'haken',        'logo', 'Startnummer', 2,    NULL, NULL, NULL, 1, 40),
  ('presse',           'Namensnennung Presse',        'silber', 'haken',        NULL,   NULL,          NULL, NULL, NULL, NULL, 1, 50),
  ('logo_shirt',       'Logo auf Lauf-Shirt',         'silber', 'haken',        'logo', 'Lauf-Shirt',  3,    NULL, NULL, NULL, 0, 60),
  ('startplaetze',     'Startplätze',                 'bronze', 'startplaetze', NULL,   NULL,          NULL, 1,    3,    5,    1, 70),
  ('banner',           'Banner im Start-/Zielbereich','gold',   'haken_text',   NULL,   NULL,          NULL, NULL, NULL, NULL, 1, 80),
  ('stand',            'eigener Stand inkl. Fläche',  'gold',   'haken',        NULL,   NULL,          NULL, NULL, NULL, NULL, 1, 90),
  ('moderation',       'Moderations-Erwähnung',       'gold',   'haken',        NULL,   NULL,          NULL, NULL, NULL, NULL, 1, 100)
ON DUPLICATE KEY UPDATE `key` = `key`;
