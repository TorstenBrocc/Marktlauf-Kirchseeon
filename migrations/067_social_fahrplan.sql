-- Social-Fahrplan: terminierter Contentplan als Einstieg der Social-Strecke.
-- Spec: intern/social-fahrplan-redesign-spec.md (Schnitt 1).
-- anlass_key referenziert src/social_anlaesse.php; post_id wird ab Schnitt 2 verknuepft.
CREATE TABLE social_fahrplan (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  anlass_key         VARCHAR(64)  NOT NULL,
  zieldatum          DATE         NULL,
  zustaendig_user_id INT UNSIGNED NULL,
  frequenz_tage      SMALLINT UNSIGNED NULL,   -- Wiederkehr: alle N Tage
  ende               DATE         NULL,        -- letztes Datum der Wiederkehr
  post_id            INT UNSIGNED NULL,        -- Verknuepfung zu post_race_contents (Schnitt 2)
  status             ENUM('offen','erledigt') NOT NULL DEFAULT 'offen',
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed: Contentplan 2026 relativ zum Renntag So 20.09.2026 (alle Termine in der UI editierbar).
-- Bereits verstrichene Themen (save_the_date, anmeldung_offen) werden nicht gesaeet.
INSERT INTO social_fahrplan (anlass_key, zieldatum, frequenz_tage, ende) VALUES
  ('helfer_gesucht',       '2026-08-18', NULL, NULL),
  ('countdown_30',         '2026-08-21', NULL, NULL),
  ('sponsorenvorstellung', '2026-08-25', 7,    '2026-09-15'),
  ('trainingstipp',        '2026-08-27', 7,    '2026-09-17'),
  ('strecke',              '2026-08-31', NULL, NULL),
  ('nachhaltigkeit',       '2026-09-04', NULL, NULL),
  ('warum_mitlaufen',      '2026-09-07', NULL, NULL),
  ('energie_umwelttag',    '2026-09-10', NULL, NULL),
  ('countdown_7',          '2026-09-13', NULL, NULL),
  ('morgen',               '2026-09-19', NULL, NULL),
  ('eventtag',             '2026-09-20', NULL, NULL),
  ('renntag',              '2026-09-21', NULL, NULL),
  ('danke',                '2026-09-21', NULL, NULL);
